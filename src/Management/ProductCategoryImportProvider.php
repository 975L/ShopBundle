<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "shop_product_category" content export (see ProductCategoryExportProvider), matched by slug - deliberately independent of the product section, which creates a named category on the fly, so the two import correctly in either order
class ProductCategoryImportProvider implements ImportProviderInterface
{
    public const string KIND = 'shop_product_category';

    public function __construct(
        private readonly BlockDataImporter $blockDataImporter,
        private readonly EntityManagerInterface $em,
        private readonly ProductCategoryRepository $productCategoryRepository,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    // $filesDir is only ever read for the files the blocks of a category page hold - the category itself carries none
    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $category = $this->productCategoryRepository->findOneBySlug($item['slug']);
            $isNew = null === $category;
            $category ??= new ProductCategory();

            $category
                ->setSlug($item['slug'])
                ->setName($item['name'])
                ->setDescription($item['description'] ?? null)
                ->setPosition($item['position'] ?? 0);

            $this->replaceBlocks($category, $item['blocks'] ?? [], $filesDir);

            $this->em->persist($category);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as ProductImportProvider
    private function replaceBlocks(ProductCategory $category, array $blocksData, ?string $filesDir): void
    {
        foreach ($category->getBlocks()->toArray() as $existingBlock) {
            $category->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $category->addBlock($block);
        }
    }
}
