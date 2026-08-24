<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

// Every kind of this bundle resolves its content live at render time (see ShopBlockExtension), which no Block/Media event ever signals a change of - so each entry carries the catalog tag ShopCacheInvalidationListener drops whenever a product, an item, a media or an affinity changes. That is what lets those kinds be cached at all rather than declared "cacheable: false", the same way SiteBundle's articles_slider is
class ShopBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    // The kinds showing one product, or a list of them, all invalidated by the same catalog tag - a finer one per product would still have to be dropped wholesale on a rename, the slug a block stores being what its tag would be built on
    private const array PRODUCT_KINDS = [
        'shop_products',
        'shop_product',
        'shop_product_button',
        'shop_product_items',
        'shop_product_slider',
        'shop_recommendations',
        // The visuals a card is bought on are products too, and their amounts are items: a card added to the catalogue or an amount withdrawn is the very change this tag is dropped on
        'shop_gift_cards',
    ];

    public function getCacheTagResolvers(): array
    {
        $resolvers = ['shop_categories' => static fn (Block $block): array => [ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES]];

        foreach (self::PRODUCT_KINDS as $kind) {
            $resolvers[$kind] = $this->resolveProducts(...);
        }

        return $resolvers;
    }

    // Null, i.e. render this block live: a listing drawing its products at random is exactly what a cached entry would freeze into one single draw until the catalog itself changes. Same veto CollectionBlockCacheTagProvider applies to a collection ordered at random
    /**
     * @return string[]|null
     */
    private function resolveProducts(Block $block): ?array
    {
        if (true === ($block->getData()['random'] ?? false)) {
            return null;
        }

        return [ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS];
    }
}
