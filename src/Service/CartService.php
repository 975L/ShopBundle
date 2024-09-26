<?php

namespace c975L\ShopBundle\Service;

use DateTime;
use c975L\ShopBundle\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use c975L\ShopBundle\Repository\CartRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use c975L\ShopBundle\Service\ProductServiceInterface;

class CartService implements CartServiceInterface
{
    private $cart;

    private $session;

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProductServiceInterface $productService,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
        $this->session = $this->requestStack->getSession();
    }

    // Adds product to cart
    public function add(Request $request): void
    {
        $this->define();

        $products = $this->cart->getProducts();
        $productId = $request->request->get('product_id');
        $quantity = $request->request->get('quantity', 1);
        $product = $this->productService->findOneById($productId);

        // Adds product to cart
        if (isset($products[$productId])) {
            $products[$productId]['quantity'] += $quantity;
            $products[$productId]['total'] = $products[$productId]['quantity'] * $product->getPrice();
        } else {
            $products[$productId] = [
                'product' => $product->toArray(),
                'quantity' => $quantity,
                'total' => $quantity * $product->getPrice(),
            ];
        }
        $this->cart->setProducts($products);
        $this->cart->setModification(new dateTime());

        $this->updateTotals();
        $this->saveDatabase();
    }

    // Gets cart from session
    public function define(): void
    {
//$this->session->remove('cart');
        $this->cart = $this->session->get('cart');
        if (null === $this->cart) {
            $this->create();
        }
    }

    // Creates cart
    public function create(): void
    {
        $this->cart = new Cart();
        $this->cart->setIdentifiant(hash('sha1', uniqid()));
        $this->cart->setPrice(0);
        $this->cart->setQuantity(0);
        $this->cart->setCurrency('€');
        $this->cart->setCreation(new DateTime());
        $this->cart->setModification(new DateTime());
        $this->cart->setStatus('new');

        $this->saveSession();
        $this->saveDatabase();
    }

    public function get(): Cart
    {
        $this->define();

        return $this->cart;
    }

    // Updates total price
    public function updateTotals(): void
    {
        $products = $this->cart->getProducts();

        $total = 0;
        $quantity = 0;
        foreach ($products as $product) {
            $total += $product['total'];
            $quantity += $product['quantity'];
        }

        $this->cart->setPrice($total);
        $this->cart->setQuantity($quantity);

        $this->saveSession();
    }

    // Saves in database
    public function saveDatabase(): void
    {
        $existingCart = $this->cartRepository->findOneByIdentifiant($this->cart->getIdentifiant());
        if ($existingCart) {
            $existingCart->setProducts($this->cart->getProducts());
            $existingCart->setPrice($this->cart->getPrice());
            $existingCart->setModification($this->cart->getModification());
            $this->em->persist($existingCart);
        } else {
            $this->em->persist($this->cart);
        }

        $this->em->flush();
    }

    // Saves cart in session
    public function saveSession(): void
    {
        $this->session->set('cart', $this->cart);
    }

    // Deletes cart in session
    public function deleteInSession(): void
    {
        $this->session->remove('cart');
    }
}