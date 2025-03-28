<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductItemFile;

interface ProductItemServiceInterface
{
    public function findOneById(int $id): ProductItem;

    public function findAllMedias();

    public function findAllFiles();

    public function deleteOneMediaByName(string $name): void;
}
