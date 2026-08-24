<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductItemStockAlert>
 */
class ProductItemStockAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductItemStockAlert::class);
    }

    public function findOneByItemAndEmail(ProductItem $productItem, string $email): ?ProductItemStockAlert
    {
        return $this->findOneBy(['productItem' => $productItem, 'email' => $email]);
    }

    /**
     * The next batch of subscriptions waiting to be told, oldest first, item and product loaded with them.
     *
     * Narrowed on what is buyable again rather than merely bounded: a row whose item never comes back would otherwise
     * sit at the head of the queue for good, filling the window run after run and hiding everyone behind it. What is
     * left out here is only what can be read in SQL - whether the item is really worth writing about stays with
     * ProductStateService, which notifyPending() asks a second time.
     *
     * @return ProductItemStockAlert[]
     */
    public function findPending(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->select('a, i, p')
            ->innerJoin('a.productItem', 'i')
            ->innerJoin('i.product', 'p')
            ->andWhere('a.notifiedAt IS NULL')
            ->andWhere('i.isPublished = true')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.availableAt IS NULL OR p.availableAt <= :now')
            // Back in stock: no cap at all, or a cap the orders have not reached. An item capped at 0 was withdrawn and never satisfies this, which is exactly what keeps it out of the window
            ->andWhere('i.limitedQuantity IS NULL OR i.limitedQuantity > COALESCE(i.orderedQuantity, 0)')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    // How many people are still waiting, which every run of c975l:shop:stock-alerts:send reports: a queue that stops going down is how a shop finds out its mailer is refusing
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.notifiedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
