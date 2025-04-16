<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Crowdfunding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Crowdfunding>
 */
class CrowdfundingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Crowdfunding::class);
    }

    // Finds all crowfundings sorted
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm')
            ->leftJoin('c.medias', 'cm')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds a crowdfunding by slug with joined data
    public function findOneBySlug(string $slug): ?Crowdfunding
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm, cc, ccm, v, cn, cct')
            ->leftJoin('c.medias', 'cm')
            ->leftJoin('c.counterparts', 'cc')
            ->leftJoin('cc.media', 'ccm')
            ->leftJoin('c.videos', 'v')
            ->leftJoin('c.news', 'cn')
            ->leftJoin('c.contributors', 'cct')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    //    /**
    //     * @return Crowdfunding[] Returns an array of Crowdfunding objects
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

    //    public function findOneBySomeField($value): ?Crowdfunding
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
