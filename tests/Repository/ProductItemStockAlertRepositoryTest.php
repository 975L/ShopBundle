<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Repository;

use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ProductItemStockAlertRepositoryTest extends TestCase
{
    // An account closed takes only the alerts of its own address, never the whole table
    public function testDeleteByEmailIsScopedToTheAddress(): void
    {
        $dql = null;

        $this->createRepository($dql)->deleteByEmail('user@example.test');

        $this->assertStringStartsWith('DELETE', (string) $dql);
        $this->assertStringContainsString('a.email = :email', (string) $dql);
    }

    // A repository wired on an entity manager that runs no query, only records the DQL it was handed
    private function createRepository(?string &$dql): ProductItemStockAlertRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('setParameters')->willReturnSelf();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(ProductItemStockAlert::class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(function (string $sentDql) use ($query, &$dql): Query {
            $dql = $sentDql;

            return $query;
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new ProductItemStockAlertRepository($registry);
    }
}
