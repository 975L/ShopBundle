<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\PaymentBundle\Service\OfferSnippetBuilder;
use c975L\PaymentBundle\Service\ShippingRateResolverInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\ShopBundle\Service\ShopBreadcrumbBuilder;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use c975L\ShopBundle\Twig\ProductJsonLdExtension;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\RatingSnippetBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What this extension hands the builder and what the graph then publishes, an item's own picture staying out of it
class ProductJsonLdExtensionTest extends TestCase
{
    private ProductJsonLdExtension $extension;

    protected function setUp(): void
    {
        $requestStack = new RequestStack([Request::create('https://example.org/shop/products/affiche')]);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn(null);

        // The trail's own urls are generated rather than routed: what this test covers is the graph, not the routing of a kernel it does not boot
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => 'https://example.org/' . $route . (isset($parameters['slug']) ? '/' . $parameters['slug'] : '')
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $ratingService = $this->createStub(RatingService::class);
        $ratingService->method('getAggregate')->willReturn(['average' => 0.0, 'count' => 0]);
        $ratingService->method('getScale')->willReturn(5);

        $this->extension = new ProductJsonLdExtension(
            new ProductSnippetBuilder(new ProductStateService(), new RatingSnippetBuilder($ratingService), new OfferSnippetBuilder($configService, $this->createStub(ShippingRateResolverInterface::class))),
            new ShopBreadcrumbBuilder(
                new ShopPublicUrlResolver($configService, new LocalizedUrlGenerator($urlGenerator, new SiteLocales(['fr'], 'fr'), $requestStack), $urlGenerator, new SiteLocales(['fr'], 'fr')),
                $translator,
            ),
            $urlGenerator,
        );
    }

    // The graph states the product's picture alone, an item's own staying small for the row that shows it
    public function testAnItemsMediaIsNotPublished(): void
    {
        $product = $this->product()->addItem($this->item('a2')->setMedia(new ProductItemMedia()->setName('medias/shop/items/affiche-a2.webp')));
        $graph = json_decode($this->extension->productJsonLd($product, 'https://example.org/affiche.webp'), true);

        $this->assertSame('https://example.org/affiche.webp', $graph['image']);
        $this->assertArrayNotHasKey('image', $graph['offers'][0]);
    }

    // Nothing to publish, nothing rendered: the sheet's <script> tag is left out entirely
    public function testAProductWithoutTitleRendersNothing(): void
    {
        $this->assertSame('', $this->extension->productJsonLd(new Product()));
    }

    private function product(): Product
    {
        return new Product()->setTitle('Affiche')->setSlug('affiche');
    }

    private function item(string $slug): ProductItem
    {
        return new ProductItem()
            ->setTitle(strtoupper($slug))
            ->setSlug($slug)
            ->setPrice(1250)
            ->setCurrency('eur')
            ->setLimitedQuantity(null);
    }

    // The listing's own graph: one element per card, pointing at the sheet that sells it
    public function testAListingPublishesEachCardAsALinkToItsSheet(): void
    {
        $graph = json_decode($this->extension->productsJsonLd([$this->product()]), true);

        $this->assertSame('ItemList', $graph['@type']);
        $this->assertSame('https://example.org/product_display/affiche', $graph['itemListElement'][0]['url']);
        $this->assertSame('Affiche', $graph['itemListElement'][0]['name']);
    }

    // A page printing no card renders no <script> at all
    public function testAnEmptyListingRendersNothing(): void
    {
        $this->assertSame('', $this->extension->productsJsonLd([]));
    }
}
