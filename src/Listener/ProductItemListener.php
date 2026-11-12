<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Places, names, dates and signs an item when it is written and when an editor really changes it - a sale counting its stock (see ProductBasketItemProvider::countOrdered()) changing neither its author nor anything else
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItem::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: ProductItem::class)]
class ProductItemListener
{
    use UserTrait;

    // What a sale writes on the item by itself, the stock it counted and when
    private const array COUNTERS = ['orderedQuantity', 'modification'];

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new item gets its placeholders, its place, its slug, its dates and its author - a creation date it already carries kept, so a row built with its own keeps it
    public function prePersist(ProductItem $entity, PrePersistEventArgs $event): void
    {
        // Needs to add empty placeholder and a add contents aftewards because the owner is not yet persisted
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

        $this->placeAndName($entity);

        if (null === $entity->getCreation()) {
            $entity->setCreation(new \DateTime());
        }
        if (null === $entity->getModification()) {
            $entity->setModification(new \DateTime());
        }

        $this->stampUser($entity, false);
    }

    // Doctrine recomputes the change set after this, so what is written here is saved with the edit
    public function preUpdate(ProductItem $entity, PreUpdateEventArgs $event): void
    {
        if (!$this->isEdited($event, self::COUNTERS)) {
            return;
        }

        $this->placeAndName($entity);
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }

    // After its product's last item, and named off its title once - a slug already given stays, so a renamed item keeps its anchor and its row in an archive (see ProductCatalogWriter)
    private function placeAndName(ProductItem $entity): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getProduct()?->getItems() ?? [] as $item) {
                $maxPosition = max($maxPosition, (int) $item->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }

        if ('' === (string) $entity->getSlug()) {
            $entity->setSlug(ProductItem::slugFor((string) $entity->getTitle()));
        }
    }
}
