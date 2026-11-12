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

    // What the block forms pick from: a template is no product to name on a page
    public function testTheBackOfficeCatalogueLeavesOutTemplates(): void
    {
        $dql = null;
        $this->createRepository($dql)->findNotDeleted();

        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
        $this->assertStringContainsString('p.template = false', (string) $dql);
    }

    // The same guarantee on the sorted listing findAll() delegates to
    public function testFindAllSortedLeavesOutHiddenAndTrashedProducts(): void
    {
        $dql = null;
        $this->createRepository($dql)->findAllSorted('newest');

        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
    }

    // The page is cut on the products alone: a LIMIT on the joined rows would cut through one product's medias and items
    public function testThePageIsCutOnTheProductsAloneAndStaysBehindTheSameGuarantee(): void
    {
        $dql = null;
        $this->createRepository($dql)->findPageSorted('newest', 12, 12);

        $this->assertStringStartsWith('SELECT p.id FROM', (string) $dql);
        $this->assertStringNotContainsString('JOIN', (string) $dql);
        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('ORDER BY p.creation DESC, p.id ASC', (string) $dql);
    }

    public function testTheTotalIsCountedBehindTheSameGuarantee(): void
    {
        $dql = null;
        $this->createRepository($dql)->countAvailable();

        $this->assertStringStartsWith('SELECT COUNT(p.id) FROM', (string) $dql);
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

    // The site search cards are drawn only from what a visitor may still buy, with the items the basket button needs read in the same query
    public function testTheSiteSearchCardsReadOnlyAvailableProductsWithTheirItems(): void
    {
        $dql = null;
        $this->createRepository($dql)->findAvailableBySlugs(['affiche']);

        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
        $this->assertStringContainsString('p.availableAt < :now', (string) $dql);
        $this->assertStringContainsString('SELECT p, m, i', (string) $dql);
        $this->assertStringContainsString('p.slug IN (:slugs)', (string) $dql);
    }

    // No slug, no query
    public function testNoSlugRunsNoQuery(): void
    {
        $dql = null;

        $this->assertSame([], $this->createRepository($dql)->findAvailableBySlugs([]));
        $this->assertNull($dql);
    }

    // What the selector, the sitemap and the category blocks list: a category is read through its available products, so an empty one is left out
    public function testTheListedCategoriesAreThoseHoldingAnAvailableProduct(): void
    {
        $dql = null;
        $this->createRepository($dql)->findListedCategories();

        $this->assertStringContainsString('SELECT DISTINCT c FROM', (string) $dql);
        $this->assertStringContainsString('INNER JOIN c.products p', (string) $dql);
        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertStringContainsString('p.isDeleted = false', (string) $dql);
        $this->assertStringContainsString('p.availableAt < :now', (string) $dql);
    }

    // The owners of a block are filtered through EXISTS and their blocks fetched whole on an alias of their own: a fetch-join on the filtered alias would load each sheet with the matching blocks only
    public function testTheOwnersOfABlockAreReadWithoutAPartialCollection(): void
    {
        $dql = null;
        $this->createRepository($dql)->findByBlockIds([3]);

        $this->assertStringStartsWith('SELECT o, ob FROM', (string) $dql);
        $this->assertStringContainsString('LEFT JOIN o.blocks ob', (string) $dql);
        $this->assertStringContainsString('EXISTS (SELECT b.id FROM', (string) $dql);
        $this->assertStringContainsString('b MEMBER OF o.blocks', (string) $dql);
    }

    // The nightly refresh looks back over a few days, among the products the shop shows
    public function testAReleaseIsLookedForOverTheDaysAmongTheShownProducts(): void
    {
        $dql = null;
        $this->createRepository($dql)->hasReleaseBetween(new \DateTimeImmutable('2026-11-28'), new \DateTimeImmutable('2026-12-01'));

        $this->assertStringContainsString('p.availableAt BETWEEN :from AND :to', (string) $dql);
        $this->assertStringContainsString('p.hidden = false', (string) $dql);
    }

    // The instant search runs on every key typed: its products are cut in SQL on the ids alone, a single letter never rendering the whole catalogue
    public function testTheSearchIsCutOnTheProductsAlone(): void
    {
        $dql = null;
        $maxResults = null;
        $this->createRepository($dql, $maxResults)->search('e');

        $this->assertStringStartsWith('SELECT p.id FROM', (string) $dql);
        $this->assertStringNotContainsString('JOIN', (string) $dql);
        $this->assertStringContainsString('p.hidden = false', (string) $dql);
        $this->assertSame(ProductRepository::SEARCH_LIMIT, $maxResults);
        // Cut in the shop's own order, so the products kept are the ones it puts first
        $this->assertStringContainsString('ORDER BY p.position ASC, p.id ASC', (string) $dql);
    }

    // A repository wired on an entity manager that runs no query, only records the DQL it was handed
    private function createRepository(?string &$dql, ?int &$maxResults = null): ProductRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->method('setMaxResults')->willReturnCallback(function (?int $max) use ($query, &$maxResults): Query {
            $maxResults = $max;

            return $query;
        });
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
