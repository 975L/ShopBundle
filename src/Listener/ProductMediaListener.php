<?php

namespace c975L\ShopBundle\Listener;

use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use c975L\ShopBundle\Listener\Traits\MediaTrait;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductMedia::class)]
class ProductMediaListener
{
    use MediaTrait;
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ProductMedia $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getProduct()->getMedias() as $media) {
                $maxPosition = max($maxPosition, $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
        $this->setUser($entity);
    }

    public function postPersist(ProductMedia $entity, PostPersistEventArgs $event): void
    {
        $this->resizeMedia($entity);
    }

    public function preRemove(ProductMedia $entity, PreRemoveEventArgs $event): void
    {
        $this->deleteMedias($entity);
    }
}