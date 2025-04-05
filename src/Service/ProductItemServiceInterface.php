<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
