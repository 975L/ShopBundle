<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use Doctrine\ORM\EntityRepository;
use c975L\ShopBundle\Entity\ProductAffinity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            ->orderBy('pa.affinityScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
