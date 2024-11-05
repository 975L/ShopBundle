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

    // Adds product to cart and returns total and quantity
    public function add(Request $request): array
    {
        $this->define();

        $data = $request->toArray();
        $products = $this->cart->getProducts();
        $productId = $data["id"];
        $quantity = $data["quantity"];
        $product = $this->productService->findOneById($productId);

        // Adds product to cart
        if (isset($products[$productId])) {
            $products[$productId]['quantity'] += $quantity;
            $products[$productId]['totalVat'] = $products[$productId]['quantity'] * $product->getVatAmount();
            $products[$productId]['total'] = $products[$productId]['quantity'] * $product->getPrice();
        } else {
            $products[$productId] = [
                'product' => $product->toArray(),
                'quantity' => $quantity,
                'totalVat' => $quantity * $product->getVatAmount(),
                'total' => $quantity * $product->getPrice(),
            ];
        }

        $this->cart->setProducts($products);
        $this->cart->setModification(new dateTime());

        $this->updateTotals();
        $this->saveDatabase();

        return [
            'total' => $this->cart->getTotal(),
            'quantity' => $this->cart->getQuantity(),
            'productQuantity' => $products[$productId]['quantity'],
        ];
    }

    // Creates cart
    public function create(): void
    {
        $this->cart = new Cart();
        $this->cart->setIdentifiant(hash('sha1', uniqid()));
        $this->cart->setTotal(0);
        $this->cart->setQuantity(0);
        $this->cart->setCurrency('€');
        $this->cart->setCreation(new DateTime());
        $this->cart->setModification(new DateTime());
        $this->cart->setStatus('new');
        $this->cart->setNumeric(true);

        $this->saveSession();
        $this->saveDatabase();
    }

    // Defines cart from session
    public function define(): void
    {
        $identifiant = $this->session->get('cart');
        if (null === $identifiant) {
            $this->create();
        } else {
            $this->cart = $this->cartRepository->findOneByIdentifiant($identifiant);
        }

        if (null === $this->cart) {
            $this->session->remove('cart');
            $this->create();
        }
    }

    // Deletes cart
    public function delete(): array
    {
        $identifiant = $this->session->get('cart');
        if (null !== $identifiant) {
            $this->cart = $this->cartRepository->findOneByIdentifiant($identifiant);

            $this->em->remove($this->cart);
            $this->em->flush();

            $this->deleteSession();
        }

        return [
            'total' => 0,
            'quantity' => 0,
        ];
    }

    // Deletes cart in session
    public function deleteSession(): void
    {
        $this->session->remove('cart');
    }

    // Returns current cart
    public function get(): Cart
    {
        $this->define();

        return $this->cart;
    }

    // Gets total and quantity
    public function getTotal(): array
    {
        $this->define();

        return [
            'total' => $this->cart->getTotal(),
            'quantity' => $this->cart->getQuantity(),
        ];
    }

    // Saves in database
    public function saveDatabase(): void
    {
        $existingCart = $this->cartRepository->findOneByIdentifiant($this->cart->getIdentifiant());
        if ($existingCart) {
            $existingCart->setProducts($this->cart->getProducts());
            $existingCart->setTotal($this->cart->getTotal());
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
        $this->session->set('cart', $this->cart->getIdentifiant());
    }

    // Updates total
    public function updateTotals(): void
    {
        $products = $this->cart->getProducts();

        $total = 0;
        $quantity = 0;
        $isNumeric = true;
        foreach ($products as $product) {
            $total += $product['total'];
            $quantity += $product['quantity'];
            if (false === is_numeric($product['product']['isNumeric'])) {
                $isNumeric = false;
            }
        }

        $this->cart->setNumeric($isNumeric);
        $this->cart->setTotal($total);
        $this->cart->setQuantity($quantity);
    }

    // Validates cart
    public function validate(Request $request): array
    {
        $this->define();

        $data = $request->request->all();
        $this->cart->setEmail($data['email']);
        if (isset($data['address'])) {
            $this->cart->setAddress(
                [
                    "address" => $data['address'],
                    "city" => $data['city'],
                    "zip" => $data['zip'],
                    "country" => $data['country'],
                    ]
                );
        }
        $this->cart->setStatus('validated');

        $this->saveSession();
        $this->saveDatabase();

        return [
            'identifiant' => $this->cart->getIdentifiant(),
            'email' => $this->cart->getEmail(),
            'total' => $this->cart->getTotal(),
            'currency' => $this->cart->getCurrency(),
        ];
    }
}