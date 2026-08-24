<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Management\ShopBlockOwnerResolver;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use PHPUnit\Framework\TestCase;

class ShopBlockOwnerResolverTest extends TestCase
{
    private function createResolver(?Product $product = null, ?ProductCategory $category = null, ?ShopSettings $settings = null): ShopBlockOwnerResolver
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('find')->willReturn($product);

        $categoryRepository = $this->createStub(ProductCategoryRepository::class);
        $categoryRepository->method('find')->willReturn($category);

        $settingsRepository = $this->createStub(ShopSettingsRepository::class);
        $settingsRepository->method('findSingle')->willReturn($settings);

        return new ShopBlockOwnerResolver($productRepository, $categoryRepository, $settingsRepository);
    }

    // The three places this bundle composes blocks in, and nothing else: a Page's own type belongs to SiteBundle, which answers for it
    public function testSupportsTheThreeOwnerTypes(): void
    {
        $resolver = $this->createResolver();

        $this->assertTrue($resolver->supports(ShopBlockOwnerResolver::TYPE_PRODUCT));
        $this->assertTrue($resolver->supports(ShopBlockOwnerResolver::TYPE_CATEGORY));
        $this->assertTrue($resolver->supports(ShopBlockOwnerResolver::TYPE_SHOP));
        $this->assertFalse($resolver->supports('page'));
    }

    public function testFindReadsEachOwnerFromItsOwnRepository(): void
    {
        $product = new Product();
        $category = new ProductCategory();
        $settings = new ShopSettings();

        $resolver = $this->createResolver($product, $category, $settings);

        $this->assertSame($product, $resolver->find(ShopBlockOwnerResolver::TYPE_PRODUCT, 7));
        $this->assertSame($category, $resolver->find(ShopBlockOwnerResolver::TYPE_CATEGORY, 3));
        $this->assertSame($settings, $resolver->find(ShopBlockOwnerResolver::TYPE_SHOP, 1));
    }

    // The shop's index holds a single row, whichever id the move screen names: the block lands on that one
    public function testFindAnswersTheSingleShopRowWhateverIdIsAsked(): void
    {
        $settings = new ShopSettings();

        $resolver = $this->createResolver(settings: $settings);

        $this->assertSame($settings, $resolver->find(ShopBlockOwnerResolver::TYPE_SHOP, 999));
    }

    public function testFindAnswersNullForAnUnknownOwnerType(): void
    {
        $this->assertNull($this->createResolver(new Product())->find('page', 7));
    }

    // A shop that never opened the screen has no row, and the move then simply finds nothing rather than failing
    public function testFindAnswersNullWhenTheShopRowWasNeverCreated(): void
    {
        $this->assertNull($this->createResolver()->find(ShopBlockOwnerResolver::TYPE_SHOP, 1));
    }
}
