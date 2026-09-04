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
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ProductRepositoryTest extends TestCase
{
    // What the shop stands behind: findAll() is overridden to hand back only the products neither hidden nor trashed, the guarantee the sitemap, the search and the blocks all lean on
    public function testFindAllLeavesOutHiddenAndTrashedProducts(): void
    {
        $dql = null;
        $this->createRepository($dql)->findAll();

        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
    }

    // The same guarantee on the sorted listing findAll() delegates to
    public function testFindAllSortedLeavesOutHiddenAndTrashedProducts(): void
    {
        $dql = null;
        $this->createRepository($dql)->findAllSorted('newest');

        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
    }

    // The file is joined with the item: every card asks each of its items whether it carries one, to say digital or physical and to name the format (see ProductStateService), which costs a query per item when the association is left to be resolved one by one
    public function testTheSortedListingReadsItsItemsFilesInTheSameQuery(): void
    {
        $dql = null;
        $this->createRepository($dql)->findAllSorted('newest');

        // Joined and selected: joined alone, the alias would filter the rows without sparing a single query
        $this->assertStringContainsString('LEFT JOIN i.file', (string) $dql);
        $this->assertStringContainsString('SELECT p, m, c, i, f', (string) $dql);
    }

    // The same, on the listing a category leads to: the cards it shows are the very same ones
    public function testTheCategoryListingReadsItsItemsFilesInTheSameQuery(): void
    {
        $dql = null;
        $this->createRepository($dql)->findByCategorySlug('histoires');

        $this->assertStringContainsString('LEFT JOIN i.file', (string) $dql);
    }

    // A repository wired on an entity manager that runs no query, only records the DQL it was handed
    private function createRepository(?string &$dql): ProductRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('getResult')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Product::class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(function (string $sentDql) use ($query, &$dql): Query {
            $dql = $sentDql;

            return $query;
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new ProductRepository($registry, $this->createStub(ProductCategoryRepository::class));
    }
}
