<?php

namespace c975L\ShopBundle\Service;

use DateTime;
use Stripe\Stripe;
use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Payment;
use c975L\ShopBundle\Entity\ProductItem;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use c975L\ShopBundle\Form\ShopFormFactoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use c975L\ShopBundle\Message\ConfirmOrderMessage;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BasketService implements BasketServiceInterface
{
    private $basket;
    private $session;
    private $stripeSecret;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $em,
        private readonly ProductItemServiceInterface $productItemService,
        private readonly RequestStack $requestStack,
        private readonly ShopFormFactoryInterface $shopFormFactory,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        try {
            $this->session = $this->requestStack->getSession();
        } catch (\LogicException $e) {
            // En contexte CLI, pas de session disponible
            $this->session = null;
        }
        $this->stripeSecret = $_ENV["STRIPE_SECRET"];
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
        $basket->setUser($this->session->get('user'));

        $this->em->persist($basket);
        $this->em->flush();
        $this->session->set('basket', $basket->getId());

        return $basket;
    }

    // Deletes basket
    public function delete(): array
    {
        $identifiant = $this->session->get('basket');
        if (null !== $identifiant) {
            $this->basket = $this->get();

            $this->em->remove($this->basket);
            $this->em->flush();

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

        $productItems = $this->basket->getProductItems();

        $total = 0;
        $quantity = 0;
        $hasDigital = false;
        $hasPhysical = false;

        foreach ($productItems as $productItem) {
            $total += $productItem['total'];
            $quantity += $productItem['quantity'];

            if (null === $productItem['productItem']['file']) {
                $hasPhysical = true;
            } else {
                $hasDigital = true;
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
        $this->em->persist($this->basket);

        // Creates payment
        $data = $this->createStripeSession();
        $this->createPayment();

        $this->em->flush();

        return $data['url'];
    }

    // Sets basket as validated after successful payment
    public function validated(Basket $basket): void
    {
        if ('validated' === $basket->getStatus()) {
            $basket->setStatus('paid');

            $this->em->persist($basket);
            $this->em->flush();

            // Dispatch messages emails
            $this->messageBus->dispatch(new ConfirmOrderMessage($basket->getId()));
            $this->messageBus->dispatch(new ProductItemDownloadMessage($basket->getId()));

            // Deletes from session
            $this->session->remove('basket');
        }
    }

    // Adds product to basket and returns total and quantity
    public function addProductItem(Request $request): array
    {
        $basket = $this->get();
        $this->basket = null === $basket ? $this->create() : $basket;

        // Here the products are in fact the productItems
        $data = $request->toArray();
        $productItems = $this->basket->getProductItems();
        $productItemId = $data["id"];
        $quantity = $data["quantity"];
        $productItem = $this->productItemService->findOneById($productItemId);

        if (null === $productItem) {
            throw new \Exception('Product not found');
        }

        // Adds productItem to basket
        if (isset($productItems[$productItemId])) {
            // Deletes productItem if quantity is 0
            if ($productItems[$productItemId]['quantity'] + $quantity <= 0) {
                unset($productItems[$productItemId]);
            // Otherwise updates quantity
            } else {
                $productItems[$productItemId]['quantity'] += $quantity;
                $productItems[$productItemId]['totalVat'] = $productItems[$productItemId]['quantity'] * $productItem->getVat();
                $productItems[$productItemId]['total'] = $productItems[$productItemId]['quantity'] * $productItem->getPrice();
            }
        // New productItem
        } else {
            $productItems = $this->defineProductItem($productItems, $productItem, $quantity);
        }

        $this->basket->setProductItems($productItems);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->em->persist($this->basket);
        $this->em->flush();

        return [
            'basket' => $this->basket->toArray(),
        ];
    }

    // Deletes product item from basket
    public function deleteProductItem(Request $request): array
    {
        $this->basket = $this->get();
        $data = $request->toArray();

        // Deletes productItem from basket
        $productItems = $this->basket->getProductItems();
        if (isset($productItems[$data["id"]])) {
            unset($productItems[$data["id"]]);
        }

        $this->basket->setProductItems($productItems);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->em->persist($this->basket);
        $this->em->flush();

        return $this->getJson();
    }

    // Defines productItem
    private function defineProductItem(array $productItems, ProductItem $productItem, int $quantity): array
    {
        // Removes values not needed in basket
        $productItemData = $productItem->toArray();
        unset($productItemData['description']);
        unset($productItemData['product']);
        unset($productItemData['creation']);
        unset($productItemData['position']);
        unset($productItemData['modification']);
        unset($productItemData['user']);
        $productItemData['file'] = $productItem->getFile()->getName();
        $productItemData['media'] = $productItem->getMedia()->getName();

        // Adds values related to product itself
        $product = $productItem->getProduct();
        $productData = [];
        $productData['title'] = $product->getTitle();
        $productData['slug'] = $product->getSlug();
        $productData['image'] = $product->getMedias()->isEmpty() ? null : $product->getMedias()[0]->getName();

        // Adds productItem to basket
        $productItems[$productItem->getId()] = [
            'productItem' => $productItemData,
            'product' => $productData,
            'quantity' => $quantity,
            'totalVat' => $quantity * $productItem->getVat(),
            'total' => $quantity * $productItem->getPrice(),
        ];

        return $productItems;
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
        $payment->setUser($this->session->get('user'));

        $this->em->persist($payment);
    }

    // Creates Stripe Session
    public function createStripeSession(): array
    {
        // Defines line items
        $lineItems = [];
        foreach ($this->basket->getProductItems() as $productItem) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->basket->getCurrency(),
                    'product_data' => [
                        'name' => $productItem['product']['title'] . ' ('. $productItem['productItem']['title'] . ')',
                    ],
                    'unit_amount' => $productItem['productItem']['price'],
                ],
                'quantity' => $productItem['quantity'],
            ];
        }

        // Adds shipping
        if ($this->basket->getShipping() > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->basket->getCurrency(),
                    'product_data' => [
                        'name' => 'Shipping',
                        'name' => $this->translator->trans('label.shipping', [], 'shop'),
                    ],
                    'unit_amount' => $this->basket->getShipping(),
                ],
                'quantity' => 1,
            ];
        }

        // Creates Stripe Session
        Stripe::setApiKey($this->stripeSecret);
        Stripe::setApiVersion('2025-01-27.acacia');
        $checkoutSession = StripeSession::create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->urlGenerator->generate('basket_validated', ['number' => $this->basket->getNumber()], $this->urlGenerator::ABSOLUTE_URL),
            'cancel_url' => $this->urlGenerator->generate('basket_validate', [], $this->urlGenerator::ABSOLUTE_URL),
            'customer_email' => $this->basket->getEmail(),
        ]);

        return [
            'id' => $checkoutSession->id,
            'url' => $checkoutSession->url,
        ];
    }

    // Deletes unvalidated baskets
    public function deleteUnvalidated(): void
    {
        $count = 0;
        $batchSize = 20;

        $baskets = $this->basketRepository->findUnvalidated(14);
        foreach ($baskets as $basket) {
            $this->em->remove($basket);
            $count++;

            // Flush every $batchSize to avoid memory issues
            if ($count % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if ($count % $batchSize !== 0) {
            $this->em->flush();
        }
    }
}
