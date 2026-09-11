<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ShopBreadcrumbBuilder;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The trail is built once and read twice - the nav above the page and the BreadcrumbList a search engine shows - so what it names is what the visitor is shown
class ShopBreadcrumbBuilderTest extends TestCase
{
    // The shop, the product's first category, then the sheet itself
    public function testAProductTrailNamesTheShopThenItsFirstCategory(): void
    {
        $trail = $this->builder(null)->forProduct($this->product());

        $this->assertSame([
            ['name' => 'label.shop', 'url' => 'https://example.org/shop'],
            ['name' => 'Salon', 'url' => 'https://example.org/shop/category/salon'],
            ['name' => 'Table basse', 'url' => 'https://example.org/shop/products/table-basse'],
        ], $trail);
    }

    // A product filed under nothing has no category level, its trail being the shop and the sheet
    public function testAProductWithoutCategoryHasNoCategoryLevel(): void
    {
        $product = new Product()->setTitle('Table basse')->setSlug('table-basse');

        $this->assertSame(['label.shop', 'Table basse'], array_column($this->builder(null)->forProduct($product), 'name'));
    }

    // The trail a visitor on "/en/..." clicks stays in English, where it used to send them back into the writing language
    public function testTheTrailFollowsTheLanguageBeingRead(): void
    {
        $trail = $this->builder('en')->forProduct($this->product());

        $this->assertSame([
            'https://example.org/en/shop',
            'https://example.org/en/shop/category/salon',
            'https://example.org/en/shop/products/table-basse',
        ], array_column($trail, 'url'));
    }

    // A category page's trail is the shop and the category, in the language being read
    public function testACategoryTrailNamesTheShopAndTheCategory(): void
    {
        $trail = $this->builder('en')->forCategory($this->category());

        $this->assertSame(
            ['https://example.org/en/shop', 'https://example.org/en/shop/category/salon'],
            array_column($trail, 'url'),
        );
    }

    // Without a configured host the trail is the one this bundle has always printed, the router taking the host from the request
    public function testTheTrailStaysAbsoluteWhileTheSiteUrlIsUnconfigured(): void
    {
        $trail = $this->builder('en', siteUrl: '')->forCategory($this->category());

        $this->assertSame(
            ['https://example.org/shop', 'https://example.org/shop/category/salon'],
            array_column($trail, 'url'),
        );
    }

    private function product(): Product
    {
        return new Product()
            ->setTitle('Table basse')
            ->setSlug('table-basse')
            ->addCategory($this->category())
        ;
    }

    private function category(): ProductCategory
    {
        return new ProductCategory()->setName('Salon')->setSlug('salon');
    }

    private function builder(?string $readingLocale, string $siteUrl = 'https://example.org'): ShopBreadcrumbBuilder
    {
        $request = Request::create('https://example.org/shop');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = [], int $type = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $path = match ($route) {
                    'shop_index' => '/shop',
                    'shop_index_localized' => '/' . $parameters['_locale'] . '/shop',
                    'product_display' => '/shop/products/' . $parameters['slug'],
                    'product_display_localized' => '/' . $parameters['_locale'] . '/shop/products/' . $parameters['slug'],
                    'category_display' => '/shop/category/' . $parameters['slug'],
                    'category_display_localized' => '/' . $parameters['_locale'] . '/shop/category/' . $parameters['slug'],
                    default => throw new RouteNotFoundException($route),
                };

                return UrlGeneratorInterface::ABSOLUTE_URL === $type ? 'https://example.org' . $path : $path;
            }
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        // The key itself as its own translation, so what the trail names can be told from what a catalog says
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $siteLocales = new SiteLocales(['fr', 'en'], 'fr');

        return new ShopBreadcrumbBuilder(
            new ShopPublicUrlResolver(
                $configService,
                new LocalizedUrlGenerator($router, $siteLocales, new RequestStack([$request])),
                $router,
                $siteLocales,
            ),
            $translator,
        );
    }
}
