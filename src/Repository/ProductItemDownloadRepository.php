<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ProductItemDownload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductItemDownload>
 */
class ProductItemDownloadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductItemDownload::class);
    }

    // The links of one basket that have not expired yet, newest first: the customer area hands out the copy already made rather than making another on every visit
    public function findLiveByBasket(int $basketId, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.basketId = :basketId')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('basketId', $basketId)
            ->setParameter('now', $now)
            ->orderBy('d.expiresAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Past its expiry date a link is spent, downloaded or not: waiting for a click that may never come would leave its copy on the disk forever
    public function findExpired(\DateTimeImmutable $expirationDate): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.expiresAt < :expirationDate')
            ->setParameter('expirationDate', $expirationDate)
            ->getQuery()
            ->getResult();
    }

    // Drops the rows expired long enough that nobody is owed the page explaining why their link no longer works
    public function deleteExpiredBefore(\DateTimeImmutable $retentionDate): int
    {
        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.expiresAt < :retentionDate')
            ->setParameter('retentionDate', $retentionDate)
            ->getQuery()
            ->execute();
    }

    //    /**
    //     * @return ProductItemDownload[] Returns an array of ProductItemDownload objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ProductItemDownload
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Of those baskets, the ones a copy was ever made for - expiry not read, a basket whose links ran out having been delivered all the same.
     *
     * @param list<int> $basketIds
     *
     * @return list<int>
     */
    public function findDeliveredBasketIds(array $basketIds): array
    {
        if ([] === $basketIds) {
            return [];
        }

        return array_map(
            static fn (array $row) => (int) $row['basketId'],
            $this->createQueryBuilder('d')
                ->select('DISTINCT d.basketId')
                ->andWhere('d.basketId IN (:basketIds)')
                ->setParameter('basketIds', $basketIds)
                ->getQuery()
                ->getScalarResult()
        );
    }
}
