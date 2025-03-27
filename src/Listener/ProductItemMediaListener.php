<?php

namespace c975L\ShopBundle\Listener;

use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductItemMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use c975L\ShopBundle\Listener\Traits\MediaTrait;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductItemMedia::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductItemMedia::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: ProductItemMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductItemMedia::class)]
class ProductItemMediaListener
{
    use MediaTrait;
    use UserTrait;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ProductItemMedia $entity, PreFlushEventArgs $event): void
    {
        $this->setUser($entity);
    }

    public function postPersist(ProductItemMedia $entity, PostPersistEventArgs $event): void
    {
        $this->resizeMedia($entity);
    }

    public function postUpdate(ProductItemMedia $entity, PostUpdateEventArgs $event): void
    {
        $this->resizeMedia($entity);
    }

    public function preRemove(ProductItemMedia $entity, PreRemoveEventArgs $event): void
    {
        $this->deleteMedia($entity);
    }
}