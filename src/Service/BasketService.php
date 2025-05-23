<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use DateTime;
use Exception;
use Stripe\Stripe;
use RuntimeException;
use DateTimeImmutable;
use Stripe\PaymentIntent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use c975L\ShopBundle\Message\ConfirmOrderMessage;
use c975L\ShopBundle\Message\ItemsShippedMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use c975L\ShopBundle\Form\ShopFormFactoryInterface;
use c975L\ShopBundle\Message\LotteryTicketsMessage;
use c975L\ShopBundle\Entity\CrowdfundingContributor;
use Symfony\Component\Messenger\MessageBusInterface;
use c975L\ShopBundle\Service\LotteryServiceInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use c975L\ShopBundle\Service\CrowdfundingServiceInterface;
use c975L\ShopBundle\Message\CrowdfundingContributionMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use c975L\ShopBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\ShopBundle\Service\CrowdfundingCounterpartServiceInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class BasketService implements BasketServiceInterface
{
    private $basket;
    private $session;
    private $stripeSecret;
    private $user;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly CrowdfundingServiceInterface $crowdfundingService,
        private readonly CrowdfundingCounterpartServiceInterface $crowdfundingCounterpartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductItemServiceInterface $productItemService,
        private readonly RequestStack $requestStack,
        private readonly ShopFormFactoryInterface $shopFormFactory,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LotteryServiceInterface $lotteryService,
    ) {
        try {
            $this->session = $this->requestStack->getSession();
        } catch (\LogicException $e) {
            $this->session = null;
        }
        $this->stripeSecret = $this->configService->getParameter('c975LShop.stripeSecret');
        $this->getUser();
    }

    // Creates basket
    public function create(): Basket
    {
        $basket = new Basket();
        $basket->setTotal(0);
        $basket->setQuantity(0);
        $basket->setCurrency($this->configService->getParameter('c975LShop.currency'));
        $basket->setShipping($this->configService->getParameter('c975LShop.shipping'));
        $basket->setCreation(new DateTime());
        $basket->setModification(new DateTime());
        $basket->setStatus('new');
        $basket->setUser($this->user);

        $this->entityManager->persist($basket);
        $this->entityManager->flush();
        $this->session->set('basket', $basket->getId());

        return $basket;
    }

    // Deletes basket
    public function delete(): array
    {
        $identifiant = $this->session->get('basket');
        if (null !== $identifiant) {
            $this->basket = $this->get();

            $this->entityManager->remove($this->basket);
            $this->entityManager->flush();

            $this->session->remove('basket');
        }

        return [
            'total' => 0,
            'quantity' => 0,
        ];
    }

    // Returns current basket
    public function get(): ?Basket
    {
        return $this->basketRepository->findOneById($this->session->get('basket'));
    }

    // Gets total and quantity
    public function getJson(): array
    {
        $this->basket = $this->get();

        return null === $this->basket ? [] : ['basket' => $this->basket->toArray()];
    }

    // Updates total
    public function updateTotals(): void
    {
        $shipping = $this->configService->getParameter('c975LShop.shipping');
        $shippingFree = $this->configService->getParameter('c975LShop.shippingFree');

        $items = $this->basket->getItems();

        $total = 0;
        $quantity = 0;
        $contentFlags = 0;

        foreach ($items as $type => $item) {
            foreach ($item as $id => $itemContent) {
                $total += $itemContent['total'];
                $quantity += $itemContent['quantity'];

                // Defines flags for items
                if ($type === 'product') {
                    if ($itemContent['item']['file'] !== null) {
                        $contentFlags |= Basket::CONTENT_FLAG_DIGITAL;
                    } else {
                        $contentFlags |= Basket::CONTENT_FLAG_PHYSICAL;
                    }
                } elseif ($type === 'crowdfunding') {
                    if ($itemContent['item']['requiresShipping'] ?? true) {
                        $contentFlags |= Basket::CONTENT_FLAG_CF_SHIPPING;
                    } else {
                        $contentFlags |= Basket::CONTENT_FLAG_CF_DIGITAL;
                    }
                }
            }
        }

        $this->basket->setContentFlags($contentFlags);
        $this->basket->setTotal($total);

        // Shipping only for physical items
        $requiresShipping = ($contentFlags & Basket::FLAG_NEEDS_SHIPPING) > 0;
        $applyShipping = $requiresShipping && $total < $shippingFree;
        $this->basket->setShipping($applyShipping ? $shipping : 0);
        $this->basket->setQuantity($quantity);
    }

    // Validates basket
    public function validate(Request $request): string
    {
        $this->basket = $this->get();
        $this->basket->setStatus('validated');
        $this->basket->setNumber($this->generateOrderNumber());
        $this->basket->setSecurityToken($this->generateSecurityToken());
        $this->entityManager->persist($this->basket);

        // Creates payment
        $data = $this->createStripeSession();
        $this->createPayment();

        $this->entityManager->flush();

        // Define contributor if any
        $this->defineContributor($request->request->all());

        // Redirects to payment
        return $data['url'];
    }

    // Sets basket as paid after successful payment
    public function paid(Basket $basket): void
    {
        if ('validated' === $basket->getStatus()) {
            // Registers contributor if any
            $this->registerContributor();

            // Updates productItem orderedQuantity if any
            $this->updateOrderedQuantity($basket);

            // Updates basket
            $basket->setStatus('paid');
            $basket->setModification(new DateTimeImmutable());

            $this->entityManager->persist($basket);
            $this->entityManager->flush();

            // Deletes from session
            $this->session->remove('basket');

            // Dispatch messages emails
            $this->sendEmails($basket);
        }
    }

    // Updates orderedQuantity for productItem and crowdfundingCounterpart
    public function updateOrderedQuantity(Basket $basket): void
    {
        $items = $basket->getItems();

        foreach ($items as $type => $item) {
            foreach ($item as $id => $itemContent) {
                if ('product' === $type) {
                    $item = $this->productItemService->findOneById($id);
                } elseif ('crowdfunding' === $type) {
                    $item = $this->crowdfundingCounterpartService->findOneById($id);
                }

                if (null === $item) {
                    continue;
                }

                $quantity = method_exists($item, 'getOrderedQuantity') ? $itemContent['quantity'] : 0;
                $item->setOrderedQuantity(($item->getOrderedQuantity() ?? 0) + $quantity);
            }
        }
    }

    // Sends emails after payment
    public function sendEmails(Basket $basket)
    {
        $contentFlags = $basket->getContentFlags();

        // Confirm order
        $this->messageBus->dispatch(new ConfirmOrderMessage($basket->getId()));

        // Digital products
        if ($contentFlags & (Basket::CONTENT_FLAG_DIGITAL | Basket::CONTENT_FLAG_CF_DIGITAL)) {
            $this->messageBus->dispatch(new ProductItemDownloadMessage($basket->getId()));
        }

        // Crowdfunding
        if ($contentFlags & (Basket::CONTENT_FLAG_CF_SHIPPING | Basket::CONTENT_FLAG_CF_DIGITAL)) {
            $this->messageBus->dispatch(new CrowdfundingContributionMessage($basket->getId()));
        }
    }

    // Sends email when physical items/counterparts are shipped
    public function itemsShipped(string $number, string $type): Basket
    {
        $basket = $this->basketRepository->findOneByNumber($number);

        if (null === $basket) {
            throw new Exception('Basket not found');
        }
        if ('shipped' !== $basket->getStatus()) {
            $items = $basket->getItems();

            // Items
            if ('product' === $type && isset($items['product'])) {
                $basket->setItemsShipped(new DateTime());
            }

            // Counterparts
            if ('crowdfunding' === $type && isset($items['crowdfunding'])) {
                $basket->setCounterpartsShipped(new DateTime());
            }

            // Check if there's items and counterparts and if both have been shipped
            if (1 === count($items) or (null !== $basket->getItemsShipped() and null !== $basket->getCounterpartsShipped())) {
                $basket->setStatus('shipped');
            }
            $basket->setModification(new DateTime());

            $this->entityManager->persist($basket);
            $this->entityManager->flush();

            // Sends email
            $this->messageBus->dispatch(new ItemsShippedMessage($basket->getId(), $type));
        }

        return $basket;
    }

    // Adds product to basket and returns total and quantity
    public function addItem(Request $request): array
    {
        $basket = $this->get();
        $this->basket = null === $basket ? $this->create() : $basket;

        $data = $request->toArray();
        $items = $this->basket->getItems();
        $itemId = $data["id"];
        $quantity = $data["quantity"];
        $type = $data["type"];

        // Selects the kind of item
        if ('product' === $type) {
            $item = $this->productItemService->findOneById($itemId);
        } else if ('crowdfunding' === $type) {
            $item = $this->crowdfundingCounterpartService->findOneById($itemId);
        }

        if (null === $item) {
            throw new Exception('Item not found');
        }

        // Checks if crowdfunding is started and not ended
        if ('crowdfunding' === $type) {
            $beginDatetime = new DateTime($item->getCrowdfunding()->getBeginDate()->format('Y-m-d 00:00:00'));
            $endDatetime = new DateTime($item->getCrowdfunding()->getEndDate()->format('Y-m-d 23:59:59'));
            if ($beginDatetime > new DateTime()) {
                return [
                    'error' => $this->translator->trans('label.crowdfunding_not_started', [], 'shop'),
                ];
            } elseif (new DateTime() > $endDatetime) {
                return [
                    'error' => $this->translator->trans('label.crowdfunding_ended', [], 'shop'),
                ];
            }
        }

        // Checks if limitedQuantity is defined and if it would be exceeded
        if ($item->getLimitedQuantity() > 0) {
            $alreadyOrdered = $item->getOrderedQuantity() ?? 0;
            $wouldBeOrdered = $alreadyOrdered + $quantity;

            // Over the limit
            if ($wouldBeOrdered > $item->getLimitedQuantity()) {
                $canAdd = $item->getLimitedQuantity() - $alreadyOrdered;

                if ($canAdd <= 0) {
                    return [
                        'error' => $this->translator->trans('label.no_more_items_available', [], 'shop'),
                    ];
                }
            }
        }

        // Adds item to basket
        if (isset($items[$type][$itemId])) {
            // Deletes item if quantity is 0
            if ($items[$type][$itemId]['quantity'] + $quantity <= 0) {
                unset($items[$type][$itemId]);
            // Otherwise updates quantity unless it's a digital item
            } elseif (false === method_exists($item, 'getFile') || $item->getFile()->getName() === null) {
                if (method_exists($item, 'getVat')) {
                    $items[$type][$itemId]['totalVat'] = $items[$type][$itemId]['quantity'] * $item->getVat();
                }
                $items[$type][$itemId]['quantity'] += $quantity;
                $items[$type][$itemId]['total'] = $items[$type][$itemId]['quantity'] * $item->getPrice();
            }
        // New item
        } else {
            $items = $this->defineItem($items, $type, $item, $quantity);
        }

        $this->basket->setItems($items);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->entityManager->persist($this->basket);
        $this->entityManager->flush();

        return [
            'basket' => $this->basket->toArray(),
        ];
    }

    // Deletes item from basket
    public function deleteItem(Request $request): array
    {
        $this->basket = $this->get();
        $data = $request->toArray();
        $type = $data["type"];

        // Deletes item from basket
        $items = $this->basket->getItems();
        if (isset($items[$type][$data["id"]])) {
            unset($items[$type][$data["id"]]);
        }

        $this->basket->setItems($items);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->entityManager->persist($this->basket);
        $this->entityManager->flush();

        return $this->getJson();
    }

    // Defines item
    public function defineItem(array $items, string $type, $item, int $quantity): array
    {
        // Removes values not needed in basket
        $itemData = $item->toArray();
        if ('crowdfunding' !== $type) {
            unset($itemData['description']);
        }
        unset($itemData['product']);
        unset($itemData['creation']);
        unset($itemData['position']);
        unset($itemData['modification']);
        unset($itemData['user']);

        // Adds values related to productItem/crowdfundingCounterpart itself
        if (method_exists($item, 'getMedia')) {
            $itemData['media'] = $item->getMedia() ? $item->getMedia()->getName() : null;
        }
        if ('product' === $type) {
            if (method_exists($item, 'getFile')) {
                $itemData['file'] = $item->getFile() ? $item->getFile()->getName() : null;
                $itemData['size'] = $item->getFile() ? $item->getFile()->getSize() : null;
            }

            $product = $item->getProduct();
            $vat = $item->getVat();
        } elseif ('crowdfunding' === $type) {
            $product = $item->getCrowdfunding();
            $vat = 0;
        }

        $productData = [];
        $productData['title'] = $product->getTitle();
        $productData['slug'] = $product->getSlug();
        $productData['image'] = $product->getMedias()->isEmpty() ? null : $product->getMedias()[0]->getName();

        // Adds item to basket
        $items[$type][$item->getId()] = [
            'item' => $itemData,
            'parent' => $productData,
            'type' => $type,
            'quantity' => $quantity,
            'totalVat' => $quantity * $vat,
            'total' => $quantity * $item->getPrice(),
        ];

        return $items;
    }

    // Defines the contributor in session
    public function defineContributor(array $data): void
    {
        $items = $this->basket->getItems();
        if (isset($items['crowdfunding'])) {
            $counterparts = $items['crowdfunding'];

            $counterpartsArray = [];
            foreach ($counterparts as $id => $counterpartData) {
                $counterpart = $this->crowdfundingCounterpartService->findOneById($counterpartData['item']['id']);
                if ($counterpart) {
                    $counterpartsArray[$counterpartData['item']['id']] = $counterpartData['quantity'];
                }
            }

            $contributor = [
                'name' => $data['coordinates']['contributorName'] ?? null,
                'message' => $data['coordinates']['contributorMessage'] ?? null,
                'email' => $this->basket->getEmail(),
                'basket_id' => $this->basket->getId(),
                'counterparts' => $counterpartsArray,
            ];

            $this->session->set('contributor', $contributor);
        }
    }

    // Registers contributos
    public function registerContributor()
    {
        $contributorData = $this->session->get('contributor');
        if (null !== $contributorData) {
            $basket = $this->basketRepository->findOneById([$contributorData['basket_id']]);

            // Creates contributor from session
            $contributor = new CrowdfundingContributor();
            $contributor->setName(empty($contributorData['name']) ? null : $contributorData['name']);
            $contributor->setMessage(empty($contributorData['message']) ? null : $contributorData['message']);
            $contributor->setEmail($contributorData['email']);
            $contributor->setCreation(new DateTimeImmutable());
            $contributor->setModification(new DateTimeImmutable());
            $contributor->setBasket($basket);

            $this->entityManager->persist($contributor);

            // Adds counterparts
            foreach ($contributorData['counterparts'] as $id => $quantity) {
                $counterpart = $this->crowdfundingCounterpartService->findOneById($id);
                if (!$counterpart) {
                    continue;
                }

                // Adds counterpart to contributor
                $contributorCounterpart = new CrowdfundingContributorCounterpart();
                $contributorCounterpart->setContributor($contributor);
                $contributorCounterpart->setCounterpart($counterpart);
                $contributorCounterpart->setQuantity($quantity);

                $this->entityManager->persist($contributorCounterpart);

                // Updates crowdfunding
                $crowdfunding = $counterpart->getCrowdfunding();
                if ($crowdfunding) {
                    $amount = $counterpart->getPrice() * $quantity;
                    $crowdfunding->setAmountAchieved($crowdfunding->getAmountAchieved() + $amount);
                    $crowdfunding->setModification(new DateTimeImmutable());
                    $crowdfunding->addContributor($contributor);

                    $contributor->setCrowdfunding($crowdfunding);

                    $this->entityManager->persist($crowdfunding);
                }

                // Generates lottery tickets if applicable
                $this->lotteryService->generateTicketsForContributor($contributor, $counterpart, $quantity);
            }

            // Sends email to contributor with tickets numbers
            $this->messageBus->dispatch(new LotteryTicketsMessage($contributor->getId()));

            $this->entityManager->flush();
            $this->session->remove('contributor');
        }
    }

    // Generates order number with format AAAAMM-YY-XXXXX
    private function generateOrderNumber(): string
    {
        // Generates a prefix on two random upper letters
        $datePart = date('Ym');
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $prefix = $letters[random_int(0, strlen($letters) - 1)] . $letters[random_int(0, strlen($letters) - 1)];

        // Random number
        $randomBytes = random_bytes(4);
        $randomPart = strtoupper(bin2hex($randomBytes));
        $randomPart = substr($randomPart, 0, 5);

        // Test part
        $testPart = strpos($this->stripeSecret, 'test') !== false ? 'TEST-' : '';

        return $testPart . $datePart . '-' . $prefix . '-' . $randomPart;
    }

    // Generates security token
    public function generateSecurityToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    // Creates form
    public function createForm(string $name, Basket $basket): Form
    {
        return $this->shopFormFactory->create($name, $basket);
    }

    // Creates payment
    public function createPayment(): void
    {
        $payment = new Payment();
        $payment->setBasket($this->basket);
        $payment->setFinished(false);
        $payment->setAmount($this->basket->getTotal() + $this->basket->getShipping());
        $payment->setCurrency($this->basket->getCurrency());
        $payment->setCreation(new \DateTime());
        $payment->setModification(new \DateTime());
        $payment->setUser($this->user);

        $this->entityManager->persist($payment);
    }

    // Creates Stripe Session
    public function createStripeSession(): array
    {
        // Defines line items
        $lineItems = [];
        foreach ($this->basket->getItems() as $type => $items) {
            foreach ($items as $id => $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => $this->basket->getCurrency(),
                        'product_data' => [
                            'name' => $item['parent']['title'] . ' ('. $item['item']['title'] . ')',
                        ],
                        'unit_amount' => $item['item']['price'],
                    ],
                    'quantity' => $item['quantity'],
                ];
            }
        }

        // Adds shipping
        if ($this->basket->getShipping() > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->basket->getCurrency(),
                    'product_data' => [
                        'name' => $this->translator->trans('label.shipping', [], 'shop'),
                    ],
                    'unit_amount' => $this->basket->getShipping(),
                ],
                'quantity' => 1,
            ];
        }

        // Creates Stripe Session
        Stripe::setApiKey($this->stripeSecret);
        $checkoutSession = StripeSession::create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->urlGenerator->generate(
                'basket_paid',
                [
                    'number' => $this->basket->getNumber(),
                    'securityToken' => $this->basket->getSecurityToken()
                ],
                $this->urlGenerator::ABSOLUTE_URL
            ),
            'cancel_url' => $this->urlGenerator->generate('basket_validate', [], $this->urlGenerator::ABSOLUTE_URL),
            'customer_email' => $this->basket->getEmail(),
            'metadata' => [
                'basket_id' => $this->basket->getId(),
                'order_number' => $this->basket->getNumber()
            ]
        ]);

        return [
            'id' => $checkoutSession->id,
            'url' => $checkoutSession->url,
        ];
    }

    // Process Stripe payment information from webhook
    public function processStripePayment($session): void
    {
        $basketId = $session->metadata->basket_id ?? null;
        if (!$basketId) {
            throw new RuntimeException('Basket ID is missing from metadata');
        }

        $basket = $this->basketRepository->findOneById($basketId);
        if ($basket === null) {
            throw new RuntimeException('Basket not found with ID: ' . $basketId);
        }

        // Update payment information
        $paymentIntent = PaymentIntent::retrieve($session->payment_intent);
        $payment = $basket->getPayment();
        if ($payment) {
            $payment->setStripeToken($paymentIntent->id);
            $payment->setStripeMethod($paymentIntent->payment_method_types[0] ?? null);
            $payment->setFinished(true);
            $payment->setModification(new DateTime());

            $this->entityManager->persist($payment);
        }

        $this->entityManager->persist($basket);
        $this->entityManager->flush();
    }

    // Deletes unvalidated baskets
    public function deleteUnvalidated(): void
    {
        $count = 0;
        $batchSize = 20;

        $baskets = $this->basketRepository->findUnvalidated(14);
        foreach ($baskets as $basket) {
            $this->entityManager->remove($basket);
            $count++;

            // Flush every $batchSize to avoid memory issues
            if ($count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        if ($count % $batchSize !== 0) {
            $this->entityManager->flush();
        }
    }

    // Gets user
    private function getUser(): void
    {
        $token = $this->tokenStorage->getToken();
        $this->user = null !== $token ? $token->getUser() : null;
    }
}
