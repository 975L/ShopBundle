<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Repository;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The categories' count of products executed on a real database: the subquery a MySQL server under ONLY_FULL_GROUP_BY accepts, run rather than only compiled
#[RequiresPhpExtension('pdo_sqlite')]
class ProductCategoryCountOnADatabaseTest extends TestCase
{
    // Each category counted with its products, those in the trash or templates left out, the search kept to the name
    public function testTheProductsAreCounted(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER));
        $config->enableNativeLazyObjects(true);
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $connection = $entityManager->getConnection();
        $product = $entityManager->getClassMetadata(Product::class);
        $category = $entityManager->getClassMetadata(ProductCategory::class);
        $join = $product->getAssociationMapping('categories');
        \assert($join instanceof \Doctrine\ORM\Mapping\ManyToManyOwningSideMapping);
        $connection->executeStatement(\sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, %s BOOLEAN, %s BOOLEAN)', $product->getTableName(), $product->getColumnName('isDeleted'), $product->getColumnName('template')));
        $connection->executeStatement(\sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, %s VARCHAR(100), %s VARCHAR(100), %s INTEGER, %s TEXT)', $category->getTableName(), $category->getColumnName('name'), $category->getColumnName('slug'), $category->getColumnName('position'), $category->getColumnName('description')));
        $joinTable = $join->joinTable;
        $connection->executeStatement(\sprintf('CREATE TABLE %s (%s INTEGER, %s INTEGER)', $joinTable->name, $joinTable->joinColumns[0]->name, $joinTable->inverseJoinColumns[0]->name));
        $connection->executeStatement(\sprintf("INSERT INTO %s VALUES (1, 'item-1', 'item-1', 0, NULL), (2, 'item-2', 'item-2', 1, NULL)", $category->getTableName()));
        $connection->executeStatement(\sprintf('INSERT INTO %s VALUES (1, 0, 0), (2, 1, 0), (3, 0, 1), (4, 0, 0)', $product->getTableName()));
        $connection->executeStatement(\sprintf('INSERT INTO %s VALUES (1, 1), (2, 1), (3, 1), (4, 1), (4, 2)', $joinTable->name));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $repository = new ProductCategoryRepository($registry);

        $rows = $repository->findWithProductCount(null, 10);
        $this->assertSame([2, 1], array_map(static fn (array $row): int => (int) $row['products'], $rows));
        $this->assertCount(1, $repository->findWithProductCount('item-2', 10));
    }
}
