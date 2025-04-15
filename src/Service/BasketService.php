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
use Symfony\Component\Messenger\MessageBusInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    ) {
        try {
            $this->session = $this->requestStack->getSession();
        } catch (\LogicException $e) {
            // En contexte CLI, pas de session disponible
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
        $basket->setDigital(true);
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
        $hasDigital = false;
        $hasPhysical = false;

        foreach ($items as $type => $item) {
            foreach ($item as $id => $itemContent) {
                $total += $itemContent['total'];
                $quantity += $itemContent['quantity'];

                if ('product' === $type && null === $itemContent['item']['file']) {
                    $hasPhysical = true;
                } elseif ('product' === $type) {
                    $hasDigital = true;
                }
            }
        }

        $digitalStatus = Basket::DIGITAL_STATUS_NONE;
        if ($hasDigital && $hasPhysical) {
            $digitalStatus = Basket::DIGITAL_STATUS_MIXED;
        } elseif ($hasDigital && !$hasPhysical) {
            $digitalStatus = Basket::DIGITAL_STATUS_FULL;
        }

        $this->basket->setDigital($digitalStatus);
        $this->basket->setTotal($total);

        $applyShipping = ($digitalStatus !== Basket::DIGITAL_STATUS_FULL && $total < $shippingFree);
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

        return $data['url'];
    }

    // Sets basket as paid after successful payment
    public function paid(Basket $basket): void
    {
        if ('validated' === $basket->getStatus()) {
            $basket->setStatus('paid');
            $basket->setModification(new DateTime());

            $this->entityManager->persist($basket);
            $this->entityManager->flush();

            // Deletes from session
            $this->session->remove('basket');

            // Dispatch messages emails
            $this->messageBus->dispatch(new ConfirmOrderMessage($basket->getId()));
            if ($basket->getDigital() === Basket::DIGITAL_STATUS_FULL || $basket->getDigital() === Basket::DIGITAL_STATUS_MIXED) {
                $this->messageBus->dispatch(new ProductItemDownloadMessage($basket->getId()));
            }
        }
    }

    // Sends email when physical items are shipped
    public function itemsShipped(string $number): Basket
    {
        $basket = $this->basketRepository->findOneByNumber($number);

        if (null === $basket) {
            throw new \Exception('Basket not found');
        }

        if ('shipped' !== $basket->getStatus()) {
            $basket->setStatus('shipped');
            $basket->setShipped(new DateTime());
            $basket->setModification(new DateTime());

            $this->entityManager->persist($basket);
            $this->entityManager->flush();

            // Sends email
            $this->messageBus->dispatch(new ItemsShippedMessage($basket->getId()));
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

        if ('product' === $type) {
            $item = $this->productItemService->findOneById($itemId);
        } else if ('counterpart' === $type) {
            $item = $this->crowdfundingCounterpartService->findOneById($itemId);
        }

        if (null === $item) {
            throw new \Exception('Item not found');
        }

        // Adds item to basket
        if (isset($items[$type][$itemId])) {
            // Deletes item if quantity is 0
            if ($items[$type][$itemId]['quantity'] + $quantity <= 0) {
                unset($items[$type][$itemId]);
            // Otherwise updates quantity unless it's a digital item
            } elseif ($item->getFile()->getName() === null) {
                $items[$type][$itemId]['quantity'] += $quantity;
                $items[$type][$itemId]['totalVat'] = $items[$type][$itemId]['quantity'] * $item->getVat();
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
        unset($itemData['description']);
        unset($itemData['product']);
        unset($itemData['creation']);
        unset($itemData['position']);
        unset($itemData['modification']);
        unset($itemData['user']);

        if (method_exists($item, 'getFile')) {
            $itemData['file'] = $item->getFile() ? $item->getFile()->getName() : null;
            $itemData['size'] = $item->getFile() ? $item->getFile()->getSize() : null;
        }
        if (method_exists($item, 'getMedia')) {
            $itemData['media'] = $item->getMedia() ? $item->getMedia()->getName() : null;
        }

        // Adds values related to product/counterpart itself
        if ('product' === $type) {
            $product = $item->getProduct();
            $vat = $item->getVat();
        } elseif ('counterpart' === $type) {
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
            'product' => $productData,
            'quantity' => $quantity,
            'totalVat' => $quantity * $vat,
            'total' => $quantity * $item->getPrice(),
        ];

        return $items;
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
                            'name' => $item[$type]['title'] . ' ('. $item['item']['title'] . ')',
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
