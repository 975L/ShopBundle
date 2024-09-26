<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use Knp\Component\Pager\PaginatorInterface;


class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly PaginatorInterface $paginator
    ) {
    }

    // Finds all
    public function findAll()
    {
        return $this->productRepository->findAll();
    }

    // Gets the stories paginated
    public function findAllPaginated($query)
    {
        return $this->paginator->paginate(
            $this->findAll(),
            $query->getInt('p', 1),
            9
        );
    }

    // Finds one by random id
    public function findOneRandom(): Product
    {
        return $this->productRepository->findOneRandom(158);
    }

    // Finds one by id
    public function findOneById(int $id): Product
    {
        return $this->productRepository->findOneById($id);
    }
}
