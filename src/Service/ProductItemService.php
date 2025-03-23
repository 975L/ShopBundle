<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductItemRepository;

class ProductItemService implements ProductItemServiceInterface
{
    public function __construct(
        private readonly ProductItemRepository $productItemRepository
    ) {
    }

    // Finds one by id
    public function findOneById(int $id): ProductItem
    {
        return $this->productItemRepository->findOneById($id);
    }
}
