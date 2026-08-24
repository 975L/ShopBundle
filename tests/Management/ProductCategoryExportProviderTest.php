<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\ProductCategoryExportProvider;
use c975L\ShopBundle\Management\ProductCategoryImportProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Management\BlockDataExporter;
use PHPUnit\Framework\TestCase;

class ProductCategoryExportProviderTest extends TestCase
{
    public function testGetKindMatchesProductCategoryImportProvider(): void
    {
        $provider = new ProductCategoryExportProvider(new BlockDataExporter(sys_get_temp_dir()), $this->createStub(ProductCategoryRepository::class));

        $this->assertSame(ProductCategoryImportProvider::KIND, $provider->getKind());
    }

    // A category holding no block carries no file either, so the archive section is metadata only
    public function testExportAllSerializesEveryCategoryFromTheRepository(): void
    {
        $category = new ProductCategory()->setSlug('affiches')->setName('Affiches')->setDescription('Nos affiches')->setPosition(5);

        $categoryRepository = $this->createMock(ProductCategoryRepository::class);
        $categoryRepository->expects($this->once())->method('findAll')->willReturn([$category]);

        $data = new ProductCategoryExportProvider(new BlockDataExporter(sys_get_temp_dir()), $categoryRepository)->exportAll();

        $this->assertSame([[
            'slug' => 'affiches',
            'name' => 'Affiches',
            'description' => 'Nos affiches',
            'position' => 5,
            'blocks' => [],
        ]], $data['items']);
        $this->assertSame([], $data['files']);
    }

    // What the editor composed on the category page travels with it, the same way a sheet's blocks travel with their product
    public function testSerializeCarriesTheBlocksComposedOnTheCategoryPage(): void
    {
        $category = new ProductCategory()->setSlug('affiches')->setName('Affiches');
        $category->addBlock(new Block()->setKind('text')->setData(['content' => 'Nos affiches']));

        $item = new ProductCategoryExportProvider(new BlockDataExporter(sys_get_temp_dir()), $this->createStub(ProductCategoryRepository::class))
            ->serialize([$category])['items'][0];

        $this->assertCount(1, $item['blocks']);
        $this->assertSame('text', $item['blocks'][0]['kind']);
    }
}
