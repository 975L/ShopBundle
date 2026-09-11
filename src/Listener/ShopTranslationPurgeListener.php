<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a row's translations away with the row, the way SiteBundle's PageTranslationPurgeListener does for a page: translations name their owner rather than pointing at it (see UiBundle's Translation), so no foreign key takes them along and a new row landing on a deleted one's id would inherit its translated name - a variant taken out of its product's collection reaching this through orphanRemoval like any other removal
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class ShopTranslationPurgeListener
{
    /** @var array<int, array{string, int}> spl_object_id => [owner, id] */
    private array $pending = [];

    public function __construct(private readonly TranslationRepository $repository)
    {
    }

    // The id is read here: Doctrine has already set it back to null by the time postRemove is dispatched
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product && !$entity instanceof ProductCategory && !$entity instanceof ProductItem && !$entity instanceof ShopSettings) {
            return;
        }

        $id = $entity->getId();
        if (null !== $id) {
            $this->pending[spl_object_id($entity)] = [$this->owner($entity), $id];
        }
    }

    // Deleted once the row really is, inside the flush's transaction - a remove() never flushed, or a flush that fails, leaves the translations where they are
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $key = spl_object_id($args->getObject());
        if (!isset($this->pending[$key])) {
            return;
        }

        [$owner, $id] = $this->pending[$key];
        unset($this->pending[$key]);

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner($owner, $id);
    }

    // The owner name ShopTranslator files the row's translations under
    private function owner(Product | ProductCategory | ProductItem | ShopSettings $entity): string
    {
        return match (true) {
            $entity instanceof Product => ShopTranslator::OWNER_PRODUCT,
            $entity instanceof ProductCategory => ShopTranslator::OWNER_CATEGORY,
            $entity instanceof ProductItem => ShopTranslator::OWNER_ITEM,
            default => ShopTranslator::OWNER_SETTINGS,
        };
    }
}
