<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItem;

interface ProductItemServiceInterface
{
    public function findOneById(int $id): ProductItem;
}
