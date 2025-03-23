<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Product;

interface ProductServiceInterface
{
    public function findAll();

    public function findAllPaginated($query);

    public function findOneById(int $id): Product;

    public function search(string $query);
}
