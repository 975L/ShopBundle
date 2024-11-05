<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Shop;
use c975L\ShopBundle\Repository\ProductRepository;
use Knp\Component\Pager\PaginatorInterface;


class ShopService implements ShopServiceInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly PaginatorInterface $paginator
    ) {
    }

    // Gets the products paginated
    public function findAllProductsPaginated($query)
    {
        return $this->paginator->paginate(
            $this->productRepository->findAll(),
            $query->getInt('p', 1),
            9
        );
    }
}
