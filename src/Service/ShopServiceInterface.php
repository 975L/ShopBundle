<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Shop;
use c975L\ShopBundle\Entity\ProductItemDownload;

interface ShopServiceInterface
{
    public function findAllProductsPaginated($query);
}
