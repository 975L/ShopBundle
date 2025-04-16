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
use c975L\ShopBundle\Entity\ProductItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ProductItem::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItem::class)]
class ProductItemListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
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
        $entity->setSlug($this->slugger->slug($entity->getTitle())->lower());
        $entity->setModification(new DateTimeImmutable());
        $this->setUser($entity);
    }

    public function prePersist(ProductItem $entity, PrePersistEventArgs $event): void
    {
        // Needs to add empty placeholder and a add contents aftewards because the owner is not yet persisted
        if (null === $entity->getMedia()) {
            $productItemMedia = new ProductItemMedia();
            $productItemMedia->setUpdatedAt(new DateTimeImmutable());
            $productItemMedia->setProductItem($entity);
            $entity->setMedia($productItemMedia);
        }
        if (null === $entity->getFile()) {
            $productItemFile = new ProductItemFile();
            $productItemFile->setUpdatedAt(new DateTimeImmutable());
            $productItemFile->setProductItem($entity);
            $entity->setFile($productItemFile);
        }
        $entity->setCreation(new DateTimeImmutable());
    }
}