<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The two tags every cached block of this bundle carries, and the one place they are dropped from. UiBundle's BlockCacheInvalidationListener only ever invalidates the changed Block itself, and knows nothing of the catalog those blocks query at render time - the same gap SiteBundle closes for its articles_slider
class ShopBlockCacheInvalidator
{
    // Carried by every kind reading the catalog: a listing, a card, a button, a slider, the items table, the recommendations
    public const string CACHE_TAG_PRODUCTS = 'shop_products';

    // Carried by the categories listing alone
    public const string CACHE_TAG_CATEGORIES = 'shop_categories';

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function invalidateProducts(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_PRODUCTS]);
    }

    // A category is renamed, added or deleted: the listing of categories changes, and so does what a products block pointing at one of them shows
    public function invalidateCategories(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_CATEGORIES, self::CACHE_TAG_PRODUCTS]);
    }
}
