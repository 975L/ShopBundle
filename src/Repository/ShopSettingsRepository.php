<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ShopSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopSettings>
 *
 * @method ShopSettings|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopSettings|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShopSettings[]    findAll()
 * @method ShopSettings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShopSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopSettings::class);
    }

    // The single row, whatever its id: a shop installed before this table existed has none, and every visitor's page then renders no block rather than failing on a row that was never created
    public function findSingle(): ?ShopSettings
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }

    /**
     * @param int[] $blockIds
     *
     * @return ShopSettings[]
     */
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->select('s, b')
            ->innerJoin('s.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }
}
