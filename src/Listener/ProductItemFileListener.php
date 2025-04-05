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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use c975L\ShopBundle\Entity\ProductItemFile;
use Doctrine\ORM\Event\PostPersistEventArgs;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use c975L\ShopBundle\Listener\Traits\MediaTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;


#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductItemFile::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductItemFile::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: ProductItemFile::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductItemFile::class)]
class ProductItemFileListener
{
    use MediaTrait;
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(ProductItemFile $entity, PreFlushEventArgs $event): void
    {
        $this->setUser($entity);
    }

    public function postPersist(ProductItemFile $entity, PostPersistEventArgs $event): void
    {
        $this->renameItemFile($entity);
    }

    public function postUpdate(ProductItemFile $entity, PostUpdateEventArgs $event): void
    {
        $this->renameItemFile($entity);
    }

    public function preRemove(ProductItemFile $entity, PreRemoveEventArgs $event): void
    {
        $this->deleteMedia($entity);
    }
}
