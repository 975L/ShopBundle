<?php

namespace c975L\ShopBundle\Listener;

use DateTime;
use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductItem;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use c975L\ShopBundle\Listener\Traits\ImageTrait;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductItem::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItem::class)]
class ProductItemListener
{
    use ImageTrait;
    use UserTrait;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ProductItem $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTime());
        $this->setUser($entity);
    }

    public function prePersist(ProductItem $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTime());
    }
}