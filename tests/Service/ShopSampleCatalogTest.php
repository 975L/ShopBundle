<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Service\ShopSampleCatalog;
use PHPUnit\Framework\TestCase;

// The one dataset the showcase renders and a demo site is seeded with - what holds here is what both of them get
class ShopSampleCatalogTest extends TestCase
{
    private ShopSampleCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new ShopSampleCatalog();
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        return array_merge(...array_column($this->catalog->getProducts(), 'items'));
    }

    // Both slugs are unique columns, so a duplicate would not fail on the showcase but on the very first demo load
    public function testProductAndItemSlugsAreUnique(): void
    {
        $products = array_column($this->catalog->getProducts(), 'slug');
        $items = array_column($this->items(), 'slug');

        $this->assertSame(array_unique($products), $products);
        $this->assertSame(array_unique($items), $items);
    }

    public function testEveryProductNamesACategoryTheCatalogDeclares(): void
    {
        $categories = array_keys($this->catalog->getCategories());

        foreach ($this->catalog->getProducts() as $product) {
            $this->assertContains($product['category'], $categories, $product['slug']);
        }
    }

    // Read through the "shop" domain rather than written as sentences, so a demo site seeded in Spanish reads as a Spanish shop
    public function testEveryVisibleTextIsATranslationKey(): void
    {
        foreach ($this->catalog->getCategories() as $slug => $nameKey) {
            $this->assertStringStartsWith('label.', $nameKey, $slug);
        }

        foreach ($this->catalog->getProducts() as $product) {
            $this->assertStringStartsWith('label.', $product['title'], $product['slug']);
            $this->assertStringStartsWith('label.', $product['description'], $product['slug']);
        }

        foreach ($this->items() as $item) {
            $this->assertStringStartsWith('label.', $item['title'], $item['slug']);
            $this->assertStringStartsWith('label.', $item['description'], $item['slug']);
        }
    }

    // The shop's own filters: a catalog with nothing in one of the three buckets leaves a filter that always answers nothing
    public function testTheThreeFormatsAreAllRepresented(): void
    {
        $items = $this->items();

        $this->assertNotEmpty(array_filter($items, fn (array $item): bool => null !== $item['file']), 'no downloaded item');
        $this->assertNotEmpty(array_filter($items, fn (array $item): bool => $item['service']), 'no service');
        $this->assertNotEmpty(array_filter($items, fn (array $item): bool => null === $item['file'] && !$item['service']), 'no posted item');
    }

    // A price-before line and a stock running out are two states a card only shows if something in the catalog carries them
    public function testSomethingCarriesAPriceBeforeAndALimitedStock(): void
    {
        $items = $this->items();

        $this->assertNotEmpty(array_filter($items, fn (array $item): bool => null !== $item['priceBefore']));
        $this->assertNotEmpty(array_filter($items, fn (array $item): bool => null !== $item['limitedQuantity']));
    }

    public function testEveryItemCarriesAPrice(): void
    {
        foreach ($this->items() as $item) {
            $this->assertGreaterThan(0, $item['price'], $item['slug']);
        }
    }

    // Written down rather than computed: a demo site reloaded between two takes of the same recorded sequence has to read the same dates back
    public function testEveryProductCarriesAFrozenCreationDate(): void
    {
        $dates = [];

        foreach (new ShopSampleCatalog()->getProducts() as $product) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $product['creation']);

            $this->assertInstanceOf(\DateTimeImmutable::class, $date, $product['slug']);
            $dates[] = $product['creation'];
        }

        // Spread rather than all stamped the same day, a catalog built in one second reading as the fixture it is
        $this->assertSame($dates, array_unique($dates));
    }
}
