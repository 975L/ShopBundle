<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

 namespace c975L\ShopBundle\Listener;

use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\CrowdfundingMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: CrowdfundingMedia::class)]
class CrowdfundingMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(CrowdfundingMedia $entity, PreFlushEventArgs $event): void
    {
        $this->setUser($entity);
    }
}