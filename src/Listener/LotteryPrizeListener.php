<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use DateTimeImmutable;
use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\LotteryPrize;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: LotteryPrize::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: LotteryPrize::class)]
class LotteryPrizeListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(LotteryPrize $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTimeImmutable());
        $this->setUser($entity);
    }

    public function prePersist(LotteryPrize $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTimeImmutable());
    }
}