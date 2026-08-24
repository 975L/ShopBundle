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
use c975L\ShopBundle\Management\ProductCategoryImportProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductCategoryImportProviderTest extends TestCase
{
    public function testSupportsImportOnlyMatchesShopProductCategoryKind(): void
    {
        $provider = new ProductCategoryImportProvider($this->createBlockDataImporter(), $this->createStub(EntityManagerInterface::class), $this->createStub(ProductCategoryRepository::class));

        $this->assertTrue($provider->supportsImport('shop_product_category'));
        $this->assertFalse($provider->supportsImport('shop_product'));
    }

    public function testImportCreatesACategoryThisEnvironmentDoesNotHold(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $repository = $this->createStub(ProductCategoryRepository::class);
        $repository->method('findOneBySlug')->willReturn(null);

        $result = new ProductCategoryImportProvider($this->createBlockDataImporter(), $em, $repository)
            ->import([['slug' => 'affiches', 'name' => 'Affiches', 'description' => 'Nos affiches', 'position' => 5]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame('affiches', $persisted[0]->getSlug());
        $this->assertSame('Nos affiches', $persisted[0]->getDescription());
    }

    // The one a product created on the fly (see ProductImportProvider::resolveCategory) is written over rather than duplicated, whichever order the two sections of a "sync all" archive were imported in
    public function testImportWritesOverTheCategoryAlreadyHeldUnderThatSlug(): void
    {
        $existing = new ProductCategory()->setSlug('affiches')->setName('affiches');

        $repository = $this->createStub(ProductCategoryRepository::class);
        $repository->method('findOneBySlug')->willReturn($existing);

        $result = new ProductCategoryImportProvider($this->createBlockDataImporter(), $this->createStub(EntityManagerInterface::class), $repository)
            ->import([['slug' => 'affiches', 'name' => 'Affiches', 'description' => null, 'position' => 0]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Affiches', $existing->getName());
    }

    // Blocks have no natural key to match on, so the collection the archive carries replaces whatever the category held
    public function testImportReplacesTheBlocksComposedOnTheCategoryPage(): void
    {
        $existing = new ProductCategory()->setSlug('affiches')->setName('Affiches');
        $existing->addBlock(new Block()->setKind('text')->setData(['content' => 'Ancien texte']));

        $repository = $this->createStub(ProductCategoryRepository::class);
        $repository->method('findOneBySlug')->willReturn($existing);

        new ProductCategoryImportProvider($this->createBlockDataImporter(), $this->createStub(EntityManagerInterface::class), $repository)
            ->import([[
                'slug' => 'affiches',
                'name' => 'Affiches',
                'blocks' => [['kind' => 'text', 'position' => 0, 'data' => ['content' => 'Nouveau texte']]],
            ]]);

        $this->assertCount(1, $existing->getBlocks());
        $this->assertSame('Nouveau texte', $existing->getBlocks()->first()->getData()['content']);
    }

    private function createBlockDataImporter(): BlockDataImporter
    {
        return new BlockDataImporter($this->createStub(EntityManagerInterface::class), $this->createStub(FormBlockDependencyRegistry::class));
    }
}
