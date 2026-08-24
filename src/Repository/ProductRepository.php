<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ProductCategoryRepository $categoryRepository,
    ) {
        parent::__construct($registry, Product::class);
    }

    // Finds products based on search
    public function search(string $query, ?string $categorySlug = null): array
    {
        if (empty($query)) {
            return [];
        }

        $qb = $this->available($this->createQueryBuilder('p'))
            ->andWhere('p.title LIKE :query OR p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($categorySlug) {
            $category = $this->categoryRepository->findOneBySlug($categorySlug);
            if ($category) {
                $qb->join('p.categories', 'c')
                    ->andWhere('c = :category')
                    ->setParameter('category', $category);
            }
        }

        return $qb->orderBy('p.title', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Overrides FindAll() to get sorted
    public function findAll(): array
    {
        return $this->findAllSorted();
    }

    // Finds all products sorted, by position or by creation date when the listing asks for it, categories and items joined here rather than left to the cards, which all read them
    public function findAllSorted(?string $sort = null): array
    {
        $qb = $this->available($this->createQueryBuilder('p'))
            ->select('p, m, c, i')
            ->leftJoin('p.medias', 'm')
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.items', 'i');

        if ('newest' === $sort) {
            $qb->orderBy('p.creation', 'DESC');
        } else {
            $qb->orderBy('p.position', 'ASC');
        }

        return $qb
            ->addOrderBy('m.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // The highest "from" price of the listing, which the price bands are cut from - an item taken offline is left out
    public function findMaxLowestItemPrice(): int
    {
        // The dearest of the products' own starting prices, and not the dearest item of the catalogue: the filter compares a band against what a card is priced "from", so bands cut from an item no product starts at would offer choices leading to an empty listing
        $rows = $this->available($this->createQueryBuilder('p'))
            ->select('MIN(i.price) AS lowest')
            ->join('p.items', 'i')
            ->andWhere('i.isPublished = true')
            ->groupBy('p.id')
            ->getQuery()
            ->getScalarResult()
        ;

        return [] === $rows ? 0 : (int) max(array_column($rows, 'lowest'));
    }

    // Finds the available products of a category, joined and sorted like the shop's own listing - a block pointing at a category shows exactly what that category's page shows
    public function findByCategorySlug(string $slug): array
    {
        // "c" filters and "c2" hydrates: fetch-joining the filtered alias would leave each card with the one category it was matched on, where the listing shows the first of them all
        return $this->available($this->createQueryBuilder('p'))
            ->select('p, m, c2, i')
            ->leftJoin('p.medias', 'm')
            ->leftJoin('p.categories', 'c2')
            ->leftJoin('p.items', 'i')
            ->join('p.categories', 'c')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('m.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds a product by slug with joined data, whatever its state - the sheet itself answers 410 for a trashed product and 404 for a draft, which it can only do once it holds the row (see ProductController)
    public function findOneBySlug(string $slug): ?Product
    {
        return $this->slugQuery($slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // The same sheet, but only if the shop stands behind it: what every block and every component naming a product by its slug reads, so a draft or a trashed product renders nothing wherever it was named
    public function findOnePublishedBySlug(string $slug): ?Product
    {
        return $this->published($this->slugQuery($slug))
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function slugQuery(string $slug): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->select('p, m, i, im, if')
            ->leftJoin('p.medias', 'm')
            ->leftJoin('p.items', 'i')
            ->leftJoin('i.media', 'im')
            ->leftJoin('i.file', 'if')
            ->andWhere('p.slug = :slug')
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->setParameter('slug', $slug)
        ;
    }

    /**
     * The products behind a set of ids, in one query and with their pictures - what a wishlist reads its cards from (see ShopFavoriteItemProvider).
     *
     * Only what a visitor may still see: a draft, something trashed or something not released yet is left out rather than drawn on a list nobody could buy from.
     *
     * @param int[] $ids
     *
     * @return Product[]
     */
    public function findAvailableByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->available($this->createQueryBuilder('p'))
            ->select('p, m')
            ->leftJoin('p.medias', 'm')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds available products excluding certain IDs (used for recommendations to exclude products already in the basket)
    public function findAvailableProductsExcluding(array $excludeIds = []): array
    {
        $qb = $this->available($this->createQueryBuilder('p'))
            ->select('p, c, i')
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.items', 'i')
            ->orderBy('p.position', 'ASC');

        if (!empty($excludeIds)) {
            $qb->andWhere('p.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeIds);
        }

        return $qb->getQuery()->getResult();
    }

    // Find products by categories excluding certain product IDs (used for category-based recommendations)
    public function findByCategoriesExcluding(array $categoryIds, array $excludeProductIds = []): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $qb = $this->available($this->createQueryBuilder('p'))
            ->select('p, c, i')
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.items', 'i')
            ->andWhere('c.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('p.position', 'ASC');

        if (!empty($excludeProductIds)) {
            $qb->andWhere('p.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeProductIds);
        }

        return $qb->getQuery()->getResult();
    }

    // Find random products for default recommendations
    public function findRandomProducts(int $limit = 4, array $excludeIds = []): array
    {
        $qb = $this->available($this->createQueryBuilder('p'))
            ->select('p, i')
            ->leftJoin('p.items', 'i');

        if (!empty($excludeIds)) {
            $qb->andWhere('p.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeIds);
        }

        $products = $qb->getQuery()->getResult();

        // Shuffle products in PHP for random selection
        shuffle($products);

        return array_slice($products, 0, $limit);
    }

    // The whole catalogue as the back-office knows it, drafts included and the recycle bin left out - what the block forms pick from, an editor composing the page of a product that is not online yet being the very reason a draft exists
    public function findNotDeleted(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isDeleted = false')
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // The last position of the catalogue, drafts and trashed rows included: a new product is placed after all of them, whatever the public sees (see ProductListener)
    public function findMaxPosition(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // Published and not trashed - what the shop stands behind, whether or not it can be bought yet
    private function published(QueryBuilder $qb, string $alias = 'p'): QueryBuilder
    {
        return $qb
            ->andWhere($alias . '.isPublished = true')
            ->andWhere($alias . '.isDeleted = false')
        ;
    }

    // What a listing shows: published, not trashed, and past its availability date when it has one
    private function available(QueryBuilder $qb, string $alias = 'p'): QueryBuilder
    {
        return $this->published($qb, $alias)
            ->andWhere($alias . '.availableAt < :now OR ' . $alias . '.availableAt IS NULL')
            ->setParameter('now', new \DateTime())
        ;
    }

    // The products owning the given blocks, each with its own blocks loaded - what ShopBlockEditUrlProvider needs to point a block back at the sheet it was composed on, in one query rather than one per block
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->select('p, b')
            ->innerJoin('p.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
