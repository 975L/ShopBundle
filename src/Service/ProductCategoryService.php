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
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;

class ProductCategoryService implements ProductCategoryServiceInterface
{
    public function __construct(
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    // Finds all
    public function findAll()
    {
        return $this->productCategoryRepository->findAll();
    }

    // Finds the categories a public screen lists, an empty one being left out
    public function findAllListed(): array
    {
        return $this->productRepository->findListedCategories();
    }

    // Finds one by slug
    public function findOneBySlug(string $slug): ?ProductCategory
    {
        return $this->productCategoryRepository->findOneBySlug($slug);
    }
}
