<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\ShopSitemapProvider;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use PHPUnit\Framework\TestCase;

class ShopSitemapProviderTest extends TestCase
{
    // A sitemap only accepts absolute urls, so an unconfigured site declares nothing at all
    public function testDeclaresNothingWithoutSiteUrl(): void
    {
        $provider = $this->createProvider('', [$this->createProduct('a-product')], []);

        $this->assertSame([], $provider->getUrls());
    }

    // The shop's own url, then one per product and one per category
    public function testDeclaresTheShopItsProductsAndItsCategories(): void
    {
        $provider = $this->createProvider(
            'https://example.com/',
            [$this->createProduct('a-product')],
            [$this->createCategory('a-category')],
        );

        $locs = array_column($provider->getUrls(), 'loc');

        $this->assertSame([
            'https://example.com/shop',
            'https://example.com/shop/products/a-product',
            'https://example.com/shop/category/a-category',
        ], $locs);
    }

    // The products are read through findAll(), the only method filtering out what the shop does not stand behind - ProductRepository::findAll() is overridden for that, see ProductRepositoryTest
    public function testReadsTheProductsThroughTheFilteredFindAll(): void
    {
        $productService = $this->createMock(ProductServiceInterface::class);
        $productService->expects($this->once())->method('findAll')->willReturn([]);

        $categoryService = $this->createStub(ProductCategoryServiceInterface::class);
        $categoryService->method('findAll')->willReturn([]);

        $provider = new ShopSitemapProvider($this->createConfigService('https://example.com'), $productService, $categoryService);
        $provider->getUrls();
    }

    private function createProvider(string $siteUrl, array $products, array $categories): ShopSitemapProvider
    {
        $productService = $this->createStub(ProductServiceInterface::class);
        $productService->method('findAll')->willReturn($products);

        $categoryService = $this->createStub(ProductCategoryServiceInterface::class);
        $categoryService->method('findAll')->willReturn($categories);

        return new ShopSitemapProvider($this->createConfigService($siteUrl), $productService, $categoryService);
    }

    private function createConfigService(string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    private function createProduct(string $slug): Product
    {
        return new Product()
            ->setTitle('A product')
            ->setSlug($slug)
            ->setDescription('What it is')
            ->setModification(new \DateTime('2026-08-30'))
        ;
    }

    private function createCategory(string $slug): ProductCategory
    {
        return new ProductCategory()
            ->setName('A category')
            ->setSlug($slug)
        ;
    }
}
