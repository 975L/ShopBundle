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
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\ShopBundle\Service\ShopBreadcrumbBuilder;
use c975L\ShopBundle\Twig\ProductJsonLdExtension;
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
use Symfony\Contracts\Translation\TranslatorInterface;

// The one thing this extension does beyond delegating: turning every item's attached media into the absolute url the graph publishes
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
            new ProductSnippetBuilder($configService, new ProductStateService(), new RatingSnippetBuilder($ratingService)),
            new ShopBreadcrumbBuilder($urlGenerator, $translator),
            new Packages(new PathPackage('/', new EmptyVersionStrategy())),
            new UrlHelper($requestStack),
            $urlGenerator,
        );
    }

    public function testAnItemsMediaReachesItsOfferAsAnAbsoluteUrl(): void
    {
        $product = $this->product()->addItem($this->item('a2')->setMedia(new ProductItemMedia()->setName('medias/shop/items/affiche-a2.webp')));

        $this->assertStringContainsString('"image":"https://example.org/medias/shop/items/affiche-a2.webp"', $this->extension->productJsonLd($product));
    }

    // An item with nothing attached publishes no image, rather than the product's own
    public function testAnItemWithoutAMediaPublishesNoImage(): void
    {
        $product = $this->product()->addItem($this->item('a2'));
        $graph = json_decode($this->extension->productJsonLd($product, 'https://example.org/affiche.webp'), true);

        $this->assertSame('https://example.org/affiche.webp', $graph['image']);
        $this->assertArrayNotHasKey('image', $graph['offers'][0]);
    }

    // An item set aside publishes no offer, so its picture has no offer to be gathered for either
    public function testAHiddenItemsMediaReachesNoOffer(): void
    {
        $product = $this->product()->addItem($this->item('a2')->setHidden(true)->setMedia(new ProductItemMedia()->setName('medias/shop/items/affiche-a2.webp')));

        $this->assertStringNotContainsString('affiche-a2.webp', $this->extension->productJsonLd($product));
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
