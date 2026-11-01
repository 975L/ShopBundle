<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ProductItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductItem>
 */
class ProductItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductItem::class);
    }

    //    /**
    //     * @return ProductItem[] Returns an array of ProductItem objects
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

    //    public function findOneBySomeField($value): ?ProductItem
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Every item a customer can be shown and charged for right now, its product and its file loaded with it.
     *
     * @return list<ProductItem>
     */
    public function findSellable(): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.product', 'p')
            ->addSelect('p')
            ->leftJoin('i.file', 'f')
            ->addSelect('f')
            ->andWhere('i.hidden = false')
            ->andWhere('p.hidden = false')
            ->andWhere('p.isDeleted = false')
            ->orderBy('p.title', \SortDirection::Ascending)
            ->addOrderBy('i.position', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    // Whether a visible article is posted rather than downloaded or served, one row read at most - an item whose file has no name is delivered like any other article
    public function hasSellableParcel(): bool
    {
        return [] !== $this->createQueryBuilder('i')
            ->select('i.id')
            ->innerJoin('i.product', 'p')
            ->leftJoin('i.file', 'f')
            ->andWhere('i.hidden = false')
            ->andWhere('p.hidden = false')
            ->andWhere('p.isDeleted = false')
            ->andWhere('f.id IS NULL OR f.name IS NULL')
            ->andWhere('i.service IS NULL OR i.service = false')
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * Every item whose file has a name, its file and its product loaded with it - whether the file is still on disk is the caller's to check.
     *
     * @return list<ProductItem>
     */
    public function findWithFile(): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.file', 'f')
            ->addSelect('f')
            ->leftJoin('i.product', 'p')
            ->addSelect('p')
            ->andWhere('f.name IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    // Counts an order against the stock, and says whether the stock still held it: a single statement, for two payments at the same second both read the same count. A null cap is no stock to run out of, and one at 0 a withdrawal this order was validated before
    public function claimOrdered(ProductItem $item, int $quantity): bool
    {
        return 1 === (int) $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE shop_product_item SET ordered_quantity = COALESCE(ordered_quantity, 0) + :quantity'
                . ' WHERE id = :id AND (limited_quantity IS NULL OR limited_quantity <= 0 OR COALESCE(ordered_quantity, 0) + :quantity <= limited_quantity)',
            ['id' => $item->getId(), 'quantity' => $quantity]
        );
    }

    // Counts an order the stock could not hold, the customer having paid for it: the count stays true, and past its cap it is what the dashboard reads
    public function addOrdered(ProductItem $item, int $quantity): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE shop_product_item SET ordered_quantity = COALESCE(ordered_quantity, 0) + :quantity WHERE id = :id',
            ['id' => $item->getId(), 'quantity' => $quantity]
        );
    }

    // How many items were sold past their stock (see ShopAlertProvider)
    public function countOversold(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.limitedQuantity > 0')
            ->andWhere('i.orderedQuantity > i.limitedQuantity')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The titles of the items sold past their stock, for the shop to know which to restock or refund.
     *
     * @return list<array{product: ?string, item: ?string}>
     */
    public function findOversoldTitles(int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->select('p.title AS product', 'i.title AS item')
            ->leftJoin('i.product', 'p')
            ->andWhere('i.limitedQuantity > 0')
            ->andWhere('i.orderedQuantity > i.limitedQuantity')
            ->orderBy('p.title', \SortDirection::Ascending)
            ->addOrderBy('i.id', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
