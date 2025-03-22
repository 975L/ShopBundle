<?php

namespace c975L\ShopBundle\Listener;

use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use c975L\ShopBundle\Listener\Traits\ImageTrait;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductMedia::class)]
class ProductMediaListener
{
    use ImageTrait;
    use UserTrait;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ProductMedia $entity, PreFlushEventArgs $event): void
    {
        $this->setUser($entity);
    }

    public function postPersist(ProductMedia $entity, PostPersistEventArgs $event): void
    {
        $this->resizeImage($entity);
    }

    public function preRemove(ProductMedia $entity, PreRemoveEventArgs $event): void
    {
        $this->deleteImage($entity);
    }
}