<?php

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Basket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Basket>
 */
class BasketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Basket::class);
    }

    public function findUnvalidated(int $days)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->andWhere('b.creation < :date')
            ->setParameter('status', 'new')
            ->setParameter('date', new \DateTime('-' . $days . ' days'))
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Basket[] Returns an array of Basket objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Basket
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
