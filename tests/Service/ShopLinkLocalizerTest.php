<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Service\ShopLinkLocalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShopLinkLocalizerTest extends TestCase
{
    // A card pointing at the shop from an English page leads into the English shop, not back into the writing language
    public function testTheIndexLinkIsReadInTheLanguageThePageAroundItIsReadIn(): void
    {
        $this->assertSame('/en/shop', $this->localizer('en')->localize('/shop'));
    }

    // A word linked inside a rich text is a link like any other
    public function testTheLinksOfARichTextAreRewritten(): void
    {
        $this->assertSame(
            'voir <a href="/en/shop">la boutique</a>',
            $this->localizer('en')->localize('voir <a href="/shop">la boutique</a>'),
        );
    }

    // A product sheet and a category are read at that language's url too: "/en" is the language the shop is read in, not a claim about the row (see ShopTranslatedLocales)
    public function testASheetIsLinkedInTheLanguageBeingRead(): void
    {
        $localizer = $this->localizer('en');

        $this->assertSame('/en/shop/products/table-basse', $localizer->localize('/shop/products/table-basse'));
        $this->assertSame('/en/shop/category/salon', $localizer->localize('/shop/category/salon'));
    }

    // The anchor and the query the url was read with are put back as they were: "/shop#products" is what this bundle's own basket card links with, and a group of sort links only ever differ by their query
    public function testTheAnchorAndTheQueryAreCarriedOver(): void
    {
        $localizer = $this->localizer('en');

        $this->assertSame('/en/shop#products', $localizer->localize('/shop#products'));
        $this->assertSame('/en/shop?order=price', $localizer->localize('/shop?order=price'));
        $this->assertSame('/en/shop/products/table-basse#avis', $localizer->localize('/shop/products/table-basse#avis'));
        $this->assertSame('/en/shop/category/salon?page=2', $localizer->localize('/shop/category/salon?page=2'));
    }

    // The basket has no localised route at all: a rule matching on the "/shop" prefix would rewrite a visitor's basket into a url nothing answers
    public function testAnotherBundlesShopPathIsGivenBackUntouched(): void
    {
        $localizer = $this->localizer('en');

        foreach (['/shop/basket/display', '/shop/basket/pay/123/abc', '/shop/terms-of-sales', '/shop/'] as $path) {
            $this->assertSame($path, $localizer->localize($path));
        }
    }

    // The no-regression contract: the language the site is written in keeps the urls it always had
    public function testTheWritingLanguageIsLeftExactlyAsItWas(): void
    {
        $this->assertSame('/shop', $this->localizer(null)->localize('/shop'));
    }

    private function localizer(?string $readingLocale): ShopLinkLocalizer
    {
        $request = Request::create('/');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

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

        return new ShopLinkLocalizer(
            new LocalizedUrlGenerator($router, new SiteLocales(['fr', 'en'], 'fr'), new RequestStack([$request])),
        );
    }
}
