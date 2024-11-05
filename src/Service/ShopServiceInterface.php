<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Shop;

interface ShopServiceInterface
{
    public function findAllProductsPaginated($query);
}
