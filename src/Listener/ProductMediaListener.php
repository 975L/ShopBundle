<?php

namespace c975L\ShopBundle\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductMedia;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use c975L\ShopBundle\Listener\Traits\ImageTrait;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductMedia::class)]
class ProductMediaListener
{
    use ImageTrait;

    public function postPersist(ProductMedia $entity, PostPersistEventArgs $event): void
    {
        $this->resizeImage($entity);
    }

    public function preRemove(ProductMedia $entity, PreRemoveEventArgs $event): void
    {
        $this->deleteImage($entity);
    }
}