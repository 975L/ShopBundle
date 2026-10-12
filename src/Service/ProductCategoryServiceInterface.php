<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductCategory;

interface ProductCategoryServiceInterface
{
    /**
     * @return ProductCategory[]
     */
    public function findAll();

    /**
     * @return ProductCategory[] the categories holding at least one available product, the only ones a public screen lists
     */
    public function findAllListed(): array;

    /**
     * @return ProductCategory|null null when no category carries that slug
     */
    public function findOneBySlug(string $slug): ?ProductCategory;
}
