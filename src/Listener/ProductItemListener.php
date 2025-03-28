<?php

namespace c975L\ShopBundle\Listener;

use DateTime;
use Doctrine\ORM\Events;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use c975L\ShopBundle\Listener\Traits\MediaTrait;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductItem::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItem::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: ProductItem::class)]
class ProductItemListener
{
    use MediaTrait;
    use UserTrait;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ProductItem $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getProduct()->getItems() as $item) {
                $maxPosition = max($maxPosition, $item->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
        $entity->setModification(new DateTime());
        $this->setUser($entity);
    }

    public function prePersist(ProductItem $entity, PrePersistEventArgs $event): void
    {
        // Adds an empty ProductItemMedia because when adding a new ProductItem without ProductItemMedia, we can't add a ProductItemMedia afterwards.
        // Because the ProductItemMediaListener->postUpdate() doesn't have access to productItem, so ProductItemMedia is persisted but not linked to ProductItem.
        // By adding an empty record we can update it later. (27/03/2025)
        // Same for /ProductItemFile
        if (null === $entity->getMedia()) {
            $productItemMedia = new ProductItemMedia();
            $productItemMedia->setUpdatedAt(new \DateTimeImmutable());
            $entity->setMedia($productItemMedia);
        }
        if (null === $entity->getFile()) {
            $productItemFile = new ProductItemFile();
            $productItemFile->setUpdatedAt(new \DateTimeImmutable());
            $entity->setFile($productItemFile);
        }
        $entity->setCreation(new DateTime());
    }

    public function preRemove(ProductItem $entity, PreRemoveEventArgs $event): void
    {
        // @TODO media file are not deleted... Can't find why (27/03/2025)
        $this->deleteMedia($entity);
    }
}