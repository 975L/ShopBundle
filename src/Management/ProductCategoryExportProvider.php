<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes ProductCategories into the shape ContentExporter/ProductCategoryImportProvider expect, shared by ProductCategoryCrudController::exportSelection() and exportAll() below - the only files a category carries are its blocks', which products belong to it travelling with the products themselves
class ProductCategoryExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly BlockDataExporter $blockDataExporter,
        private readonly ProductCategoryRepository $productCategoryRepository,
    ) {
    }

    public function getKind(): string
    {
        return ProductCategoryImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->productCategoryRepository->findAll());
    }

    /**
     * @param iterable<ProductCategory> $categories
     */
    public function serialize(iterable $categories): array
    {
        $files = [];
        $items = [];
        foreach ($categories as $category) {
            $items[] = [
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'position' => $category->getPosition(),
                'blocks' => $this->blockDataExporter->exportBlocks($category->getBlocks(), $files),
            ];
        }

        return ['items' => $items, 'files' => $files];
    }
}
