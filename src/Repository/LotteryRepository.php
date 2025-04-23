<?php

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Lottery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LotteryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lottery::class);
    }
}