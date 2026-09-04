<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use c975L\ShopBundle\Service\ShopBlockCacheTagProvider;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

// What lets this bundle's kinds be cached at all: the tag their entry carries, and the one case where caching is declined
class ShopBlockCacheTagProviderTest extends TestCase
{
    private array $resolvers;

    protected function setUp(): void
    {
        $this->resolvers = $this->provider()->getCacheTagResolvers();
    }

    // Every kind of the bundle but the search, which declares "cacheable: false" and is never asked
    public function testEveryCachedKindOfTheBundleHasItsResolver(): void
    {
        $this->assertSame(
            ['shop_categories', 'shop_products', 'shop_product', 'shop_product_button', 'shop_product_items', 'shop_product_slider', 'shop_recommendations', 'shop_gift_cards'],
            array_keys($this->resolvers)
        );
        $this->assertArrayNotHasKey('shop_search', $this->resolvers);
    }

    public function testAProductKindIsInvalidatedByTheCatalogTag(): void
    {
        foreach (['shop_products', 'shop_product', 'shop_product_button', 'shop_product_items', 'shop_product_slider', 'shop_recommendations', 'shop_gift_cards'] as $kind) {
            $this->assertSame([ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS], $this->resolvers[$kind]($this->block()), $kind);
        }
    }

    // A product edited leaves the categories listing exactly as it was, so it is not dropped along with the rest
    public function testTheCategoriesListingCarriesItsOwnTagAlone(): void
    {
        $this->assertSame([ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES], $this->resolvers['shop_categories']($this->block()));
    }

    // A cached entry would freeze the draw until the catalog itself changes, which is the opposite of what asking for a random order says
    public function testAListingDrawnAtRandomDeclinesItsCacheEntry(): void
    {
        $this->assertNull($this->resolvers['shop_products']($this->block(['random' => true])));
        $this->assertNotNull($this->resolvers['shop_products']($this->block(['random' => false])));
    }

    // The day a scheduled product goes on sale, no row is saved and no tag is dropped - an entry cached the day before would hold it out of the listing for good
    public function testAScheduledProductDeclinesTheEntryOfTheListingsReadingADate(): void
    {
        $resolvers = $this->provider(true)->getCacheTagResolvers();

        foreach (['shop_products', 'shop_gift_cards', 'shop_recommendations'] as $kind) {
            $this->assertNull($resolvers[$kind]($this->block()), $kind);
        }
    }

    // A sheet's own kinds read the product they are placed on whatever its date (see ShopBlockExtension::getProduct), and the categories listing reads no product at all
    public function testTheKindsReadingNoDateAreCachedWhateverIsScheduled(): void
    {
        $resolvers = $this->provider(true)->getCacheTagResolvers();

        foreach (['shop_product', 'shop_product_button', 'shop_product_items', 'shop_product_slider'] as $kind) {
            $this->assertSame([ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS], $resolvers[$kind]($this->block()), $kind);
        }

        $this->assertSame([ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES], $resolvers['shop_categories']($this->block()));
    }

    // Three products blocks on the same page ask the same question once, and ask it again once the request is over
    public function testTheAnswerIsReadOncePerRequest(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->exactly(2))->method('hasScheduled')->willReturn(false);

        $provider = new ShopBlockCacheTagProvider($productRepository);
        $resolvers = $provider->getCacheTagResolvers();

        $resolvers['shop_products']($this->block());
        $resolvers['shop_products']($this->block());
        $resolvers['shop_gift_cards']($this->block());

        $provider->reset();
        $resolvers['shop_products']($this->block());
    }

    private function provider(bool $scheduled = false): ShopBlockCacheTagProvider
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('hasScheduled')->willReturn($scheduled);

        return new ShopBlockCacheTagProvider($productRepository);
    }

    private function block(array $data = []): Block
    {
        $block = new Block();
        $block->setData($data);

        return $block;
    }
}
