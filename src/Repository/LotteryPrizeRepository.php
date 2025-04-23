<?php

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\LotteryPrize;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LotteryPrizeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotteryPrize::class);
    }
}