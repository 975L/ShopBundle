<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use DateTime;
use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\Crowdfunding;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Crowdfunding::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Crowdfunding::class)]
class CrowdfundingListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(Crowdfunding $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTime());
        $this->setUser($entity);
    }

    public function prePersist(Crowdfunding $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTime());
    }
}
