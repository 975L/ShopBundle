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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The stock's statements executed on a real database, on a table holding the columns the entity really maps to: a column named wrong fails here and nowhere else
#[RequiresPhpExtension('pdo_sqlite')]
class ProductItemStockOnADatabaseTest extends TestCase
{
    private EntityManager $entityManager;

    private ProductItemRepository $repository;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

        $metadata = $this->entityManager->getClassMetadata(ProductItem::class);
        $id = $metadata->getColumnName('id');
        $limited = $metadata->getColumnName('limitedQuantity');
        $ordered = $metadata->getColumnName('orderedQuantity');
        $title = $metadata->getColumnName('title');
        $productId = $metadata->getSingleAssociationJoinColumnName('product');
        $this->entityManager->getConnection()->executeStatement(\sprintf('CREATE TABLE %s (%s INTEGER PRIMARY KEY, %s SMALLINT, %s SMALLINT, %s VARCHAR(255), %s INTEGER)', $metadata->getTableName(), $id, $limited, $ordered, $title, $productId));

        $product = $this->entityManager->getClassMetadata(Product::class);
        $this->entityManager->getConnection()->executeStatement(\sprintf('CREATE TABLE %s (%s INTEGER PRIMARY KEY, %s VARCHAR(255))', $product->getTableName(), $product->getColumnName('id'), $product->getColumnName('title')));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new ProductItemRepository($registry, $this->createStub(ShopBlockCacheInvalidator::class));
    }

    public function testTheStockDecidesAndTheCountStaysTrue(): void
    {
        $item = $this->storedItem(5, limited: 3, ordered: 2);

        $this->assertTrue($this->repository->claimOrdered($item, 1));
        $this->assertSame(3, $this->ordered());
        $this->assertFalse($this->repository->claimOrdered($item, 1));
        $this->assertSame(3, $this->ordered());

        $this->repository->addOrdered($item, 1);
        $this->assertSame(4, $this->ordered());
    }

    public function testNoCapAndAWithdrawnCapRefuseNothingAndANullCountStartsAtZero(): void
    {
        $item = $this->storedItem(5, limited: null, ordered: null);
        $this->assertTrue($this->repository->claimOrdered($item, 2));
        $this->assertSame(2, $this->ordered());

        $this->entityManager->getConnection()->executeStatement('UPDATE shop_product_item SET limited_quantity = 0');
        $this->assertTrue($this->repository->claimOrdered($item, 2));
        $this->assertSame(4, $this->ordered());
    }

    public function testTheOversoldCountReadsOnlyAStockPastItsCap(): void
    {
        $this->storedItem(1, limited: 3, ordered: 4);
        $this->storedItem(2, limited: 3, ordered: 3);
        $this->storedItem(3, limited: null, ordered: 9);
        $this->storedItem(4, limited: 0, ordered: 9);

        $this->assertSame(1, $this->repository->countOversold());
    }

    public function testTheOversoldItemsAreNamedByTheirProductAndTheirOwnTitle(): void
    {
        $this->entityManager->getConnection()->insert('shop_product', ['id' => 1, 'title' => 'Chaise']);
        $this->entityManager->getConnection()->insert('shop_product_item', ['id' => 1, 'limited_quantity' => 3, 'ordered_quantity' => 4, 'title' => 'Standard', 'product_id' => 1]);
        $this->entityManager->getConnection()->insert('shop_product_item', ['id' => 2, 'limited_quantity' => 3, 'ordered_quantity' => 3, 'title' => 'Sold out, not oversold', 'product_id' => 1]);
        $this->entityManager->getConnection()->insert('shop_product_item', ['id' => 3, 'limited_quantity' => 3, 'ordered_quantity' => 5, 'title' => 'Second', 'product_id' => 1]);

        $this->assertSame([['product' => 'Chaise', 'item' => 'Standard'], ['product' => 'Chaise', 'item' => 'Second']], $this->repository->findOversoldTitles(5));
        $this->assertCount(1, $this->repository->findOversoldTitles(1));
    }

    // A refund gives back what it counted and never takes the count below 0, an empty count left alone
    public function testARefundGivesTheStockBackWithoutGoingBelowZero(): void
    {
        $this->storedItem(5, limited: 3, ordered: 3);

        $this->repository->releaseOrdered(5, 2);
        $this->assertSame(1, $this->ordered());

        $this->repository->releaseOrdered(5, 4);
        $this->assertSame(0, $this->ordered());

        $this->repository->releaseOrdered(5, 1);
        $this->assertSame(0, $this->ordered());
    }

    private function storedItem(int $id, ?int $limited, ?int $ordered): ProductItem
    {
        $this->entityManager->getConnection()->insert('shop_product_item', ['id' => $id, 'limited_quantity' => $limited, 'ordered_quantity' => $ordered]);

        $item = new ProductItem();
        new \ReflectionProperty(ProductItem::class, 'id')->setValue($item, $id);

        return $item;
    }

    private function ordered(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT ordered_quantity FROM shop_product_item');
    }
}
