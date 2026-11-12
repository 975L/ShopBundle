<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ProductAffinity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ShopBundle\Entity\ProductAffinity>
 */
class ProductAffinityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAffinity::class);
    }

    // Finds products with affinity to the given product, sorted by descending affinity score
    public function findRelatedProducts(int $productId, int $limit = 10): array
    {
        return $this->createQueryBuilder('pa')
            ->select('IDENTITY(pa.product2) as relatedProductId', 'pa.affinityScore')
            ->where('pa.product1 = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('pa.affinityScore', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Every score involving one of these products, read in one query and keyed "minId_maxId" the way the pairs are stored (see getAffinityScore()) - what a page scoring a whole catalogue against a basket reads once rather than once per pair
    /**
     * @param list<int> $productIds
     *
     * @return array<string, float>
     */
    public function getScoresFor(array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('pa')
            ->select('IDENTITY(pa.product1) AS product1', 'IDENTITY(pa.product2) AS product2', 'pa.affinityScore')
            ->where('pa.product1 IN (:ids) OR pa.product2 IN (:ids)')
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getArrayResult();

        $scores = [];
        foreach ($rows as $row) {
            $scores[self::pairKey((int) $row['product1'], (int) $row['product2'])] = (float) $row['affinityScore'];
        }

        return $scores;
    }

    // The key a pair is found under whichever way round it is asked
    public static function pairKey(int $productId1, int $productId2): string
    {
        return min($productId1, $productId2) . '_' . max($productId1, $productId2);
    }

    // Gets the affinity score between two products
    public function getAffinityScore(int $productId1, int $productId2): ?float
    {
        // Always search with smaller ID first (matching storage order in Command)
        $minId = min($productId1, $productId2);
        $maxId = max($productId1, $productId2);

        $result = $this->createQueryBuilder('pa')
            ->select('pa.affinityScore')
            ->where('pa.product1 = :minId')
            ->andWhere('pa.product2 = :maxId')
            ->setParameter('minId', $minId)
            ->setParameter('maxId', $maxId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['affinityScore'] : null;
    }
}
