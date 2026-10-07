<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Repository;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ProductItemRepositoryTest extends TestCase
{
    // What a catalog import reads: only the items with a named file, their file and product joined rather than lazy-loaded one by one
    public function testFindWithFileJoinsTheFileAndTheProduct(): void
    {
        $dql = null;
        $this->createRepository($dql)->findWithFile();

        $this->assertStringContainsString('INNER JOIN i.file f', (string) $dql);
        $this->assertStringContainsString('LEFT JOIN i.product p', (string) $dql);
        $this->assertStringContainsString('f.name IS NOT NULL', (string) $dql);
    }

    // What the shipping health check reads: one visible article that is neither a named file nor a service, a single row asked for
    public function testHasSellableParcelLooksForOneVisibleArticleThatIsPosted(): void
    {
        $dql = null;
        $this->assertFalse($this->createRepository($dql)->hasSellableParcel());

        $this->assertStringContainsString('LEFT JOIN i.file f', (string) $dql);
        $this->assertStringContainsString('i.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
        $this->assertStringContainsString('f.id IS NULL OR f.name IS NULL', (string) $dql);
        $this->assertStringContainsString('i.service IS NULL OR i.service = false', (string) $dql);
    }

    private function createRepository(?string &$dql): ProductItemRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('getResult')->willReturn([]);
        $query->method('getScalarResult')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(ProductItem::class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(function (string $sentDql) use ($query, &$dql): Query {
            $dql = $sentDql;

            return $query;
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new ProductItemRepository($registry);
    }
}
