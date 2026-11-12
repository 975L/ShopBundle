<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductAffinity;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\PostFlushTagInvalidationTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Drops the cached renders of this bundle's blocks whenever the catalog they read changes - a product edited, any of its medias, an item a paid basket raised the ordered quantity of, an affinity recomputed, the currency the prices are printed in - postPersist as much as postUpdate, a brand new item on a cached product being an INSERT. The tags are gathered during the flush and dropped once it is committed (see UiBundle's PostFlushTagInvalidationTrait): dropped inside it, a concurrent request would cache the old state again
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class ShopCacheInvalidationListener
{
    use PostFlushTagInvalidationTrait;

    // The currency every cached card, button and items table prints its prices in (see ProductStateService::getCurrency())
    private const string CURRENCY_SLUG = 'shop-currency';

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queueTags($this->tagsOf($args->getObject()));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queueTags($this->tagsOf($args->getObject()));
    }

    // Read before the row is gone, dropped once its removal is committed
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->queueTags($this->tagsOf($args->getObject()));
    }

    // The pool ShopBlockCacheInvalidator drops its tags from
    private function tagCache(): TagAwareCacheInterface
    {
        return $this->cache;
    }

    // The tags a change of this row drops, none for a row the catalog does not read
    /** @return string[] */
    private function tagsOf(object $entity): array
    {
        return match (true) {
            $entity instanceof Config => self::CURRENCY_SLUG === $entity->getSlug() ? ShopBlockCacheInvalidator::PRODUCT_TAGS : [],
            $entity instanceof ProductCategory => ShopBlockCacheInvalidator::CATEGORY_TAGS,
            $entity instanceof Product,
            $entity instanceof ProductItem,
            $entity instanceof Media,
            $entity instanceof ProductAffinity => ShopBlockCacheInvalidator::PRODUCT_TAGS,
            // A language screen writes Translation rows alone, its fields being unmapped: the product or category it translates has no changeset and fires none of the arms above
            $entity instanceof Translation && ShopTranslator::OWNER_CATEGORY === $entity->getOwnerType() => ShopBlockCacheInvalidator::CATEGORY_TAGS,
            $entity instanceof Translation && \in_array($entity->getOwnerType(), [ShopTranslator::OWNER_PRODUCT, ShopTranslator::OWNER_ITEM], true) => ShopBlockCacheInvalidator::PRODUCT_TAGS,
            default => [],
        };
    }
}
