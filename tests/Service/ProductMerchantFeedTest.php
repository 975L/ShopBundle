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
use c975L\PaymentBundle\Service\ShippingRateResolverInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Service\ProductImageUrlResolver;
use c975L\ShopBundle\Service\ProductMerchantFeed;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\RatingSnippetBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The feed is the sheet's graph in Merchant Center's words: what Google refuses is left out, and every field is the one the page states
class ProductMerchantFeedTest extends TestCase
{
    // A posted book and an audiobook are listed, an EPUB and a free item are not
    public function testOnlyThePaidItemsGoogleAcceptsAreListed(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Livre imprimé', 500));
        $product->addItem($this->item(2, 'Fichier audio', 300, 'loup.mp3'));
        $product->addItem($this->item(3, 'Fichier EPUB', 300, 'loup.epub'));
        $product->addItem($this->item(4, 'Livret PDF offert', 0));

        $this->assertSame(['shop-item-1', 'shop-item-2'], array_column($this->feed([$product])->items($product), 'g:id'));
    }

    // Each field is read off the graph: the canonical url without the item's anchor, the price with its currency, the barcode and the shipping
    public function testAnItemCarriesTheSheetsFields(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Livre imprimé', 500)->setGtin('9782953285901')->setWeight(250));

        $fields = $this->feed([$product])->items($product)[0];

        $this->assertSame('shop-product-7', $fields['g:item_group_id']);
        $this->assertSame('Le Loup - Livre imprimé', $fields['g:title']);
        $this->assertSame('https://example.org/product_display/le-loup', $fields['g:link']);
        $this->assertSame('5.00 EUR', $fields['g:price']);
        $this->assertSame('in_stock', $fields['g:availability']);
        $this->assertSame('9782953285901', $fields['g:gtin']);
        $this->assertSame(['g:country' => 'FR', 'g:price' => '4.50 EUR'], $fields['g:shipping']);
        $this->assertArrayNotHasKey('g:identifier_exists', $fields);
    }

    // An item with neither barcode nor brand says so, which Merchant Center asks rather than flagging it
    public function testAnItemWithoutIdentifierSaysSo(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Jeu de cartes', 900));

        $this->assertSame('no', $this->feed([$product])->items($product)[0]['g:identifier_exists']);
    }

    // The whole document: Google's namespace, and nothing at all while the site's url is not configured
    public function testTheFeedIsGooglesRssUnlessTheSiteHasNoUrl(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Livre imprimé', 500));

        $xml = (string) $this->feed([$product])->build();
        $this->assertStringContainsString('xmlns:g="' . ProductMerchantFeed::NAMESPACE . '"', $xml);
        $this->assertStringContainsString('<g:id>shop-item-1</g:id>', $xml);

        $this->assertNull($this->feed([$product], null)->build());
    }

    // Each feed holds the products written in its language, one without any being written in the site's own
    public function testEachFeedHoldsTheProductsWrittenInItsLanguage(): void
    {
        $french = $this->product();
        $french->addItem($this->item(1, 'Livre imprimé', 500));
        $english = new Product()->setTitle('The Wolf')->setSlug('the-wolf')->setLanguage('en');
        $english->addItem($this->item(2, 'Printed book', 500));
        $feed = $this->feed([$french, $english]);

        $this->assertStringContainsString('shop-item-1', (string) $feed->build());
        $this->assertStringNotContainsString('shop-item-2', (string) $feed->build());
        $this->assertStringContainsString('shop-item-2', (string) $feed->build('en'));
        $this->assertStringNotContainsString('shop-item-1', (string) $feed->build('en'));
    }

    // One url per language a product is written in, the site's own without parameter, and none while the site has no url
    public function testOneUrlPerLanguageWritten(): void
    {
        $french = $this->product();
        $english = new Product()->setTitle('The Wolf')->setSlug('the-wolf')->setLanguage('en');

        $this->assertSame([
            'fr' => 'https://example.org/shop_merchant_feed',
            'en' => 'https://example.org/shop_merchant_feed?language=en',
        ], $this->feed([$french, $english])->urls());
        $this->assertSame([], $this->feed([$french], null)->urls());
    }

    // A shop in test mode has no feed, like it has no sitemap
    public function testBuildsNothingInTestMode(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Livre imprimé', 500));

        $this->assertNull($this->feed([$product], testMode: true)->build());
    }

    // Each offer goes with its own item, found by its sku or its slug, and one priced in no currency is not listed
    public function testOffersArePairedWithTheirItemsBySku(): void
    {
        $product = $this->product();
        $product->addItem($this->item(1, 'Livre imprimé', 500)->setSku('LOUP-PAPIER'));
        $product->addItem($this->item(2, 'Livre relié', 900));
        $product->addItem($this->item(3, 'Livre ancien', 700)->setCurrency(''));

        $items = $this->feed([$product])->items($product);

        $this->assertSame(['shop-item-1', 'shop-item-2'], array_column($items, 'g:id'));
        $this->assertSame('9.00 EUR', $items[1]['g:price']);
    }

    /** @param list<Product> $products */
    private function feed(array $products, ?string $siteUrl = 'https://example.org', bool $testMode = false): ProductMerchantFeed
    {
        $config = ['site-url' => $siteUrl, 'site-name' => 'Editions', 'shop-shipping-country' => 'fr', 'shop-currency' => 'eur', 'shop-test-mode' => $testMode];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $config[$key] ?? null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . (isset($parameters['slug']) ? '/' . $parameters['slug'] : '') . (isset($parameters['language']) ? '?language=' . $parameters['language'] : '')
        );
        $requestStack = new RequestStack([Request::create('https://example.org/shop/merchant-feed.xml')]);
        $siteLocales = new SiteLocales(['fr'], 'fr');

        $ratingService = $this->createStub(RatingService::class);
        $ratingService->method('getAggregate')->willReturn(['average' => 0.0, 'count' => 0]);
        $shippingRateResolver = $this->createStub(ShippingRateResolverInterface::class);
        $shippingRateResolver->method('resolve')->willReturn(450);

        $productService = $this->createStub(ProductServiceInterface::class);
        $productService->method('findAll')->willReturn($products);

        return new ProductMerchantFeed(
            $configService,
            $productService,
            new ProductSnippetBuilder($configService, new ProductStateService(), new RatingSnippetBuilder($ratingService), $shippingRateResolver),
            new ProductImageUrlResolver(new Packages(new PathPackage('/', new EmptyVersionStrategy())), new UrlHelper($requestStack)),
            new ShopPublicUrlResolver($configService, new LocalizedUrlGenerator($urlGenerator, $siteLocales, $requestStack), $urlGenerator, $siteLocales),
            $siteLocales,
        );
    }

    private function product(): Product
    {
        $product = new Product()->setTitle('Le Loup')->setSlug('le-loup');
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 7);

        return $product;
    }

    private function item(int $id, string $title, int $price, ?string $file = null): ProductItem
    {
        $item = new ProductItem()->setTitle($title)->setSlug('item-' . $id)->setPrice($price)->setCurrency('eur');
        new \ReflectionProperty(ProductItem::class, 'id')->setValue($item, $id);
        if (null !== $file) {
            $item->setFile(new ProductItemFile()->setName('medias/' . $file));
        }

        return $item;
    }
}
