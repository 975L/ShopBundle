<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The two tags every cached block of this bundle carries, and the one place they are dropped from. UiBundle's BlockCacheInvalidationListener only ever invalidates the changed Block itself, and knows nothing of the catalog those blocks query at render time - the same gap SiteBundle closes for its articles_slider. Dropped at once when no transaction is open, held otherwise until it is over (the next flush outside one, the end of the request, the command or the message): dropped inside it, a concurrent request would cache the uncommitted state again, with no expiry
#[AsDoctrineListener(event: Events::postFlush)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'drain')]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'drain')]
#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'drain')]
class ShopBlockCacheInvalidator
{
    // Carried by every kind reading the catalog: a listing, a card, a button, a slider, the items table, the recommendations
    public const string CACHE_TAG_PRODUCTS = 'shop_products';

    // Carried by the categories listing alone
    public const string CACHE_TAG_CATEGORIES = 'shop_categories';

    // What a catalog change drops, and what a category change drops: the products blocks pointing at a category read it too
    public const array PRODUCT_TAGS = [self::CACHE_TAG_PRODUCTS];
    public const array CATEGORY_TAGS = [self::CACHE_TAG_CATEGORIES, self::CACHE_TAG_PRODUCTS];

    /** @var array<string, true> */
    private array $pendingTags = [];

    // The connection is optional so the service can be built outside Doctrine, every invalidation then being immediate
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly ?Connection $connection = null,
    ) {
    }

    // A product, an item, a picture or an affinity changed: every block reading the catalog
    public function invalidateProducts(): void
    {
        $this->queue(self::PRODUCT_TAGS);
    }

    // A category is renamed, added or deleted: the listing of categories changes, and so does what a products block pointing at one of them shows
    public function invalidateCategories(): void
    {
        $this->queue(self::CATEGORY_TAGS);
    }

    // After a flush: dropped once no transaction holds it any more, a flush inside one (a payment's delivery) waiting for the end of the request
    public function postFlush(): void
    {
        if (true !== $this->connection?->isTransactionActive()) {
            $this->drain();
        }
    }

    // Drops every tag held, each once - the request, the command or the message being over, whatever transaction there was is too
    public function drain(): void
    {
        if ([] === $this->pendingTags) {
            return;
        }

        $tags = array_keys($this->pendingTags);
        $this->pendingTags = [];
        $this->cache->invalidateTags($tags);
    }

    // Holds the tags, dropped at once when no transaction is open
    /** @param string[] $tags */
    private function queue(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->pendingTags[$tag] = true;
        }

        $this->postFlush();
    }
}
