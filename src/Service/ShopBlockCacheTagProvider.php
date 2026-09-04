<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use Symfony\Contracts\Service\ResetInterface;

// Every kind of this bundle resolves its content live at render time (see ShopBlockExtension), which no Block/Media event ever signals a change of - so each entry carries the catalog tag ShopCacheInvalidationListener drops whenever a product, an item, a media or an affinity changes. That is what lets those kinds be cached at all rather than declared "cacheable: false", the same way SiteBundle's articles_slider is
class ShopBlockCacheTagProvider implements BlockCacheTagProviderInterface, ResetInterface
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

    // The kinds reading the catalogue as it stands today: their products are filtered on "availableAt < now" (see ProductRepository::available()), so one dated ahead joins the listing on its own day, with nobody saving anything
    private const array SCHEDULED_KINDS = [
        'shop_products',
        'shop_gift_cards',
        'shop_recommendations',
    ];

    // Read at most once per request: a page carrying three products blocks asks the same question three times on a cold cache, and the answer cannot change inside one render
    private ?bool $scheduled = null;

    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    public function getCacheTagResolvers(): array
    {
        $resolvers = ['shop_categories' => static fn (Block $block): array => [ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES]];

        foreach (self::PRODUCT_KINDS as $kind) {
            $scheduled = in_array($kind, self::SCHEDULED_KINDS, true);
            $resolvers[$kind] = fn (Block $block): ?array => $this->resolveProducts($block, $scheduled);
        }

        return $resolvers;
    }

    // Dropped between two requests, a worker runtime (FrankenPHP, RoadRunner...) keeping this service alive from one to the next - a product going on sale would otherwise stay "scheduled" for as long as the process lives
    public function reset(): void
    {
        $this->scheduled = null;
    }

    // Null, i.e. render this block live, in two cases. A listing drawing its products at random, which a cached entry would freeze into one single draw until the catalog itself changes - the same veto CollectionBlockCacheTagProvider applies to a collection ordered at random. And, for the listings reading a date, a product waiting to go on sale: an entry never expires (see BlockExtension, $item->expiresAfter(null)) and no event fires the day that date comes round, so the entry would hold the product back for good. Nothing scheduled, nothing to go stale, and putting a date on a product is a save, which drops the tag and puts this very question back
    /**
     * @return string[]|null
     */
    private function resolveProducts(Block $block, bool $scheduled = false): ?array
    {
        if (true === ($block->getData()['random'] ?? false)) {
            return null;
        }

        if ($scheduled && ($this->scheduled ??= $this->productRepository->hasScheduled())) {
            return null;
        }

        return [ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS];
    }
}
