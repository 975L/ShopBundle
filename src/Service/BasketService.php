<?php

namespace c975L\ShopBundle\Service;

use DateTime;
use Stripe\Stripe;
use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use c975L\ShopBundle\Form\ShopFormFactoryInterface;
use c975L\ShopBundle\Service\EmailServiceInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private readonly EmailServiceInterface $emailService,
        private readonly ProductServiceInterface $productService,
        private readonly RequestStack $requestStack,
        private readonly ShopFormFactoryInterface $shopFormFactory,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        $this->session = $this->requestStack->getSession();
        $this->stripeSecret = $_ENV["STRIPE_SECRET"];
    }

    // Adds product to basket and returns total and quantity
    public function add(Request $request): array
    {
        $basket = $this->get();
        $this->basket = null === $basket ? $this->create() : $basket;

        $data = $request->toArray();
        $products = $this->basket->getProducts();
        $productId = $data["id"];
        $quantity = $data["quantity"];
        $product = $this->productService->findOneById($productId);

        if (null === $product) {
            throw new \Exception('Product not found');
        }

        // Adds product to basket
        if (isset($products[$productId])) {
            if ($products[$productId]['quantity'] + $quantity <= 0) {
                unset($products[$productId]);
            } else {
                $products[$productId]['quantity'] += $quantity;
                $products[$productId]['totalVat'] = $products[$productId]['quantity'] * $product->getVatAmount();
                $products[$productId]['total'] = $products[$productId]['quantity'] * $product->getPrice();
            }
        } else {
            $products[$productId] = [
                'product' => $product->toArray(),
                'quantity' => $quantity,
                'totalVat' => $quantity * $product->getVatAmount(),
                'total' => $quantity * $product->getPrice(),
            ];
        }

        $this->basket->setProducts($products);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->em->persist($this->basket);
        $this->em->flush();

        return [
            'basket' => $this->basket->toArray(),
        ];
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
        $basket->setNumeric(true);
        $basket->setUser($this->session->get('user'));

        $this->em->persist($basket);
        $this->em->flush();
        $this->session->set('basket', $basket->getId());

        return $basket;
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
        foreach ($this->basket->getProducts() as $product) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->basket->getCurrency(),
                    'product_data' => [
                        'name' => $product['product']['title'],
                    ],
                    'unit_amount' => $product['product']['price'],
                ],
                'quantity' => $product['quantity'],
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
            'success_url' => $this->urlGenerator->generate('basket_validated', [], $this->urlGenerator::ABSOLUTE_URL),
            'cancel_url' => $this->urlGenerator->generate('basket_display', [], $this->urlGenerator::ABSOLUTE_URL),
        ]);

        return [
            'id' => $checkoutSession->id,
            'url' => $checkoutSession->url,
        ];
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

    // Deletes product from basket
    public function deleteProduct(Request $request): array
    {
        $this->basket = $this->get();
        $data = $request->toArray();

        // Deletes product from basket
        $products = $this->basket->getProducts();
        if (isset($products[$data["id"]])) {
            unset($products[$data["id"]]);
        }

        $this->basket->setProducts($products);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->em->persist($this->basket);
        $this->em->flush();

        return $this->getJson();
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

        $products = $this->basket->getProducts();

        $total = 0;
        $quantity = 0;
        $isNumeric = true;
        foreach ($products as $product) {
            $total += $product['total'];
            $quantity += $product['quantity'];
            if (null === $product['product']['file']) {
                $isNumeric = false;
            }
        }

        $this->basket->setNumeric($isNumeric);
        $this->basket->setTotal($total);
        $this->basket->setShipping(false === $isNumeric && $total < $shippingFree ? $shipping : 0);
        $this->basket->setQuantity($quantity);
    }

    // Validates basket
    public function validate(Request $request): string
    {
        $this->basket = $this->get();

        $data = $this->createStripeSession();
        $this->basket->setStatus('validated');
        $number = substr(hash('sha1', uniqid()), 10, 10);
        $number = strpos($this->stripeSecret, 'test') !== false ? substr_replace($number, "T_", 0, 2) : $number;
        $this->basket->setNumber($number);

        // Creates payment
        $this->createPayment();

        $this->em->persist($this->basket);
        $this->em->flush();

        return $data['url'];
    }

    // Validates basket
    public function validated(): ?Basket
    {
        $this->basket = $this->get();
        if (null !== $this->basket) {
            $this->basket->setStatus('paid');

            $this->em->persist($this->basket);
            $this->em->flush();

            // Sends email
            $this->emailService->send($this->basket);

            $this->session->remove('basket');
        }

        return $this->basket;
    }
}
