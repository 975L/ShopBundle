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
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use c975L\ShopBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: CrowdfundingCounterpart::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingCounterpart::class)]
class CrowdfundingCounterpartListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    public function preFlush(CrowdfundingCounterpart $entity, PreFlushEventArgs $event): void
    {
        $entity->setSlug($this->slugger->slug($entity->getTitle())->lower());
        $entity->setModification(new DateTimeImmutable());
        $this->setUser($entity);
    }

    public function prePersist(CrowdfundingCounterpart $entity, PrePersistEventArgs $event): void
    {
        // Needs to add empty placeholder and a add contents aftewards because the owner is not yet persisted
        if (null === $entity->getMedia()) {
            $crowdfundingCounterpartMedia = new CrowdfundingCounterpartMedia();
            $crowdfundingCounterpartMedia->setUpdatedAt(new DateTimeImmutable());
            $crowdfundingCounterpartMedia->setCrowdfundingCounterpart($entity);
            $entity->setMedia($crowdfundingCounterpartMedia);
        }
        $entity->setCreation(new DateTimeImmutable());
    }
}