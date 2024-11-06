<?php

namespace c975L\ShopBundle\Service;

use DateTime;
use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use c975L\ShopBundle\Form\ShopFormFactoryInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;

class BasketService implements BasketServiceInterface
{
    private $basket;

    private $session;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ProductServiceInterface $productService,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly ShopFormFactoryInterface $shopFormFactory
    ) {
        $this->session = $this->requestStack->getSession();
    }

    // Adds product to basket and returns total and quantity
    public function add(Request $request): array
    {
        $this->define();

        $data = $request->toArray();
        $products = $this->basket->getProducts();
        $productId = $data["id"];
        $quantity = $data["quantity"];
        $product = $this->productService->findOneById($productId);

        // Adds product to basket
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

        $this->basket->setProducts($products);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->saveDatabase();

        return [
            'total' => $this->basket->getTotal(),
            'quantity' => $this->basket->getQuantity(),
            'productQuantity' => $products[$productId]['quantity'],
        ];
    }

    // Creates basket
    public function create(): void
    {
        $this->basket = new Basket();
        $this->basket->setIdentifiant(hash('sha1', uniqid()));
        $this->basket->setTotal(0);
        $this->basket->setQuantity(0);
        $this->basket->setCurrency('€');
        $this->basket->setCreation(new DateTime());
        $this->basket->setModification(new DateTime());
        $this->basket->setStatus('new');
        $this->basket->setNumeric(true);

        $this->saveSession();
        $this->saveDatabase();
    }

    // Creates form
    public function createForm(string $name, Basket $basket): Form
    {
        return $this->shopFormFactory->create($name, $basket);
    }

    // Defines basket from session
    public function define(): void
    {
        $identifiant = $this->session->get('basket');
        if (null === $identifiant) {
            $this->create();
        } else {
            $this->basket = $this->basketRepository->findOneByIdentifiant($identifiant);
        }

        if (null === $this->basket) {
            $this->session->remove('basket');
            $this->create();
        }
    }

    // Deletes basket
    public function delete(): array
    {
        $identifiant = $this->session->get('basket');
        if (null !== $identifiant) {
            $this->basket = $this->basketRepository->findOneByIdentifiant($identifiant);

            $this->em->remove($this->basket);
            $this->em->flush();

            $this->deleteSession();
        }

        return [
            'total' => 0,
            'quantity' => 0,
        ];
    }

    // Deletes prodcut from basket
    public function deleteProduct(Request $request): array
    {
        $this->define();
        $data = $request->toArray();

        $products = $this->basket->getProducts();
        $productId = $data["id"];
        $quantity = $data["quantity"];
        $product = $this->productService->findOneById($productId);

        // Deletes product from basket
        if (isset($products[$productId])) {
            unset($products[$productId]);
        }

        $this->basket->setProducts($products);
        $this->basket->setModification(new dateTime());

        $this->updateTotals();
        $this->saveSession();
        $this->saveDatabase();

        return [
            'total' => $this->basket->getTotal(),
            'quantity' => $this->basket->getQuantity(),
        ];
    }

    // Deletes basket in session
    public function deleteSession(): void
    {
        $this->session->remove('basket');
    }

    // Returns current basket
    public function get(): Basket
    {
        $this->define();

        return $this->basket;
    }

    // Gets total and quantity
    public function getTotal(): array
    {
        $this->define();

        return [
            'total' => $this->basket->getTotal(),
            'quantity' => $this->basket->getQuantity(),
        ];
    }

    // Saves in database
    public function saveDatabase(): void
    {
        $existingBasket = $this->basketRepository->findOneByIdentifiant($this->basket->getIdentifiant());
        if ($existingBasket) {
            $existingBasket->setProducts($this->basket->getProducts());
            $existingBasket->setTotal($this->basket->getTotal());
            $existingBasket->setModification($this->basket->getModification());
            $this->em->persist($existingBasket);
        } else {
            $this->em->persist($this->basket);
        }

        $this->em->flush();
    }

    // Saves basket in session
    public function saveSession(): void
    {
        $this->session->set('basket', $this->basket->getIdentifiant());
    }

    // Updates total
    public function updateTotals(): void
    {
        $products = $this->basket->getProducts();

        $total = 0;
        $quantity = 0;
        $isNumeric = true;
        foreach ($products as $product) {
            $total += $product['total'];
            $quantity += $product['quantity'];
            if (false === $product['product']['isNumeric']) {
                $isNumeric = false;
            }
        }

        $this->basket->setNumeric($isNumeric);
        $this->basket->setTotal($total);
        $this->basket->setQuantity($quantity);
    }

    // Validates basket
    public function validate(Request $request): array
    {
        $this->define();

        $data = $request->request->all();
        $this->basket->setEmail($data['email']);
        if (isset($data['address'])) {
            $this->basket->setAddress(
                [
                    "address" => $data['address'],
                    "city" => $data['city'],
                    "zip" => $data['zip'],
                    "country" => $data['country'],
                    ]
                );
        }
        $this->basket->setStatus('validated');

        $this->saveSession();
        $this->saveDatabase();

        return [
            'identifiant' => $this->basket->getIdentifiant(),
            'email' => $this->basket->getEmail(),
            'total' => $this->basket->getTotal(),
            'currency' => $this->basket->getCurrency(),
        ];
    }
}