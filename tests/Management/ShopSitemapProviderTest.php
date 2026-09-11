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
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\ShopSitemapProvider;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use c975L\ShopBundle\Service\ShopTranslatedLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShopSitemapProviderTest extends TestCase
{
    // A sitemap only accepts absolute urls, so an unconfigured site declares nothing at all
    public function testDeclaresNothingWithoutSiteUrl(): void
    {
        $provider = $this->createProvider('', [$this->createProduct('a-product')], []);

        $this->assertSame([], $provider->getUrls());
    }

    // A site declaring one language keeps the sitemap it has always had, byte for byte
    public function testASingleLanguageSiteDeclaresNoAlternates(): void
    {
        $provider = $this->createProvider(
            'https://example.com',
            [$this->createProduct('a-product')],
            [$this->createCategory('a-category')],
            ['fr'],
        );

        foreach ($provider->getUrls() as $url) {
            $this->assertSame([], $url['alternates']);
        }
    }

    // Each screen is declared once per language it answers in, every entry carrying the whole group: a language's url is only ever crawled if the sitemap names it
    public function testEachScreenIsDeclaredOncePerLanguageWithItsWholeGroup(): void
    {
        $provider = $this->createProvider(
            'https://example.com',
            [$this->createProduct('a-product')],
            [$this->createCategory('a-category')],
            ['fr', 'en'],
        );

        $urls = $provider->getUrls();

        $this->assertSame([
            'https://example.com/shop',
            'https://example.com/en/shop',
            'https://example.com/shop/products/a-product',
            'https://example.com/en/shop/products/a-product',
            'https://example.com/shop/category/a-category',
            'https://example.com/en/shop/category/a-category',
        ], array_column($urls, 'loc'));

        $this->assertSame(
            ['fr' => 'https://example.com/shop', 'en' => 'https://example.com/en/shop'],
            $urls[0]['alternates'],
        );
        $this->assertSame($urls[0]['alternates'], $urls[1]['alternates']);
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

        $provider = new ShopSitemapProvider(
            $this->createResolver('https://example.com', ['fr']),
            new ShopTranslatedLocales(new SiteLocales(['fr'], 'fr')),
            $productService,
            $categoryService,
        );
        $provider->getUrls();
    }

    private function createProvider(string $siteUrl, array $products, array $categories, array $locales = ['fr']): ShopSitemapProvider
    {
        $productService = $this->createStub(ProductServiceInterface::class);
        $productService->method('findAll')->willReturn($products);

        $categoryService = $this->createStub(ProductCategoryServiceInterface::class);
        $categoryService->method('findAll')->willReturn($categories);

        return new ShopSitemapProvider(
            $this->createResolver($siteUrl, $locales),
            new ShopTranslatedLocales(new SiteLocales($locales, 'fr')),
            $productService,
            $categoryService,
        );
    }

    // The routes are generated rather than routed: what this test covers is what the sitemap declares, not the routing of a kernel it does not boot
    private function createResolver(string $siteUrl, array $locales): ShopPublicUrlResolver
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => match ($route) {
                'shop_index' => '/shop',
                'shop_index_localized' => '/' . $parameters['_locale'] . '/shop',
                'product_display' => '/shop/products/' . $parameters['slug'],
                'product_display_localized' => '/' . $parameters['_locale'] . '/shop/products/' . $parameters['slug'],
                'category_display' => '/shop/category/' . $parameters['slug'],
                'category_display_localized' => '/' . $parameters['_locale'] . '/shop/category/' . $parameters['slug'],
                default => throw new RouteNotFoundException($route),
            }
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $siteLocales = new SiteLocales($locales, 'fr');

        return new ShopPublicUrlResolver(
            $configService,
            new LocalizedUrlGenerator($router, $siteLocales, new RequestStack()),
            $router,
            $siteLocales,
        );
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
