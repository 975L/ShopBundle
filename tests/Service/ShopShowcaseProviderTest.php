<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ShopSampleCatalog;
use c975L\ShopBundle\Service\ShopShowcaseProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// What the block showcase is handed for each of the eight kinds - the templates query the catalog live, so everything here is a stand-in, and it has to be the very type those templates read
class ShopShowcaseProviderTest extends TestCase
{
    /** @var list<array{template: string, context: array}> */
    private array $rendered;

    protected function setUp(): void
    {
        $this->rendered = [];
    }

    // A grid of broken frames says less than no showcase at all
    public function testASiteDeclaringNoPlaceholderImageGetsNoShowcase(): void
    {
        $this->assertSame([], $this->createProvider([])->getShowcases());
    }

    public function testTheNineKindsAreShown(): void
    {
        $kinds = array_column($this->createProvider()->getShowcases(), 'kind');

        $this->assertSame(
            ['shop_products', 'shop_gift_cards', 'shop_categories', 'shop_product', 'shop_product_button', 'shop_recommendations', 'shop_product_items', 'shop_product_slider', 'shop_search'],
            $kinds
        );
    }

    public function testEveryShowcaseCarriesADescriptionAndOneVariant(): void
    {
        foreach ($this->createProvider()->getShowcases() as $label => $showcase) {
            $this->assertNotSame('', $showcase['description'], $label);
            $this->assertSame([''], array_keys($showcase['variants']), $label);
        }
    }

    // The regression this test exists for: the components read their badge, price and formats through shop_product_state(), typed on Product, where an array only gets as far as a TypeError
    public function testTheProductsHandedToTheTemplatesAreRealEntities(): void
    {
        $this->createProvider()->getShowcases();

        $products = $this->contextOf('@c975LShop/components/Product/Products.html.twig')['products'];
        $this->assertContainsOnlyInstancesOf(Product::class, $products);
        $this->assertInstanceOf(Product::class, $this->contextOf('@c975LShop/components/Product/Product.html.twig')['product']);
        $this->assertInstanceOf(Product::class, $this->contextOf('@c975LShop/components/Product/Button.html.twig')['product']);
        $this->assertContainsOnlyInstancesOf(Product::class, $this->contextOf('@c975LShop/components/Product/Recommendations.html.twig')['recommendations']);
    }

    public function testTheItemsHandedToTheTemplatesAreRealEntities(): void
    {
        $this->createProvider()->getShowcases();

        $this->assertContainsOnlyInstancesOf(ProductItem::class, $this->contextOf('@c975LShop/components/Product/Items.html.twig')['items']);
    }

    // Every stand-in product shows a price and a format, which it can only read off an item of its own
    public function testEveryStandInProductCarriesAPictureAndAnItem(): void
    {
        $this->createProvider()->getShowcases();

        foreach ($this->contextOf('@c975LShop/components/Product/Products.html.twig')['products'] as $product) {
            $this->assertCount(1, $product->getMedias());
            $this->assertCount(1, $product->getItems());
        }
    }

    // The column defaults to 0, which is what an item withdrawn from sale says: every button of the showcase would render disabled
    public function testAStandInItemIsLeftUnlimitedRatherThanOutOfStock(): void
    {
        $this->createProvider()->getShowcases();

        foreach ($this->contextOf('@c975LShop/components/Product/Items.html.twig')['items'] as $item) {
            $this->assertNull($item->getLimitedQuantity());
        }
    }

    // One physical item and one downloaded one, taken from the catalog so enriching it never silently drops one of the two
    public function testTheItemsShowAPostedOneAndADownloadedOne(): void
    {
        $this->createProvider()->getShowcases();

        $items = $this->contextOf('@c975LShop/components/Product/Items.html.twig')['items'];
        $this->assertCount(2, $items);
        $this->assertNull($items[0]->getFile()?->getName());
        $this->assertNotNull($items[1]->getFile()?->getName());
    }

    // The slider reads its images with vich_uploader_asset(), which needs the entity rather than a path
    public function testTheSliderIsHandedTheMediaEntitiesTheAttacherBuilds(): void
    {
        $this->createProvider()->getShowcases();

        $this->assertContainsOnlyInstancesOf(Media::class, $this->contextOf('@c975LUi/components/Slider/Slider.html.twig')['media']);
    }

    // A product slider leafs through the photographs of one article, so it asks for the first sample product's own, keyed by the site under its slug - not the generic pool, which is a row of unrelated landscapes
    public function testTheSliderLeafsThroughTheFirstProductsOwnPhotographs(): void
    {
        $slug = new ShopSampleCatalog()->getProducts()[0]['slug'];
        $keyed = ['showcase/shop/' . $slug . '-1.webp', 'showcase/shop/' . $slug . '-2.webp', 'showcase/shop/' . $slug . '-3.webp'];

        $this->createProvider(keyed: [$slug => $keyed])->getShowcases();

        $medias = $this->contextOf('@c975LUi/components/Slider/Slider.html.twig')['media'];
        $this->assertSame($keyed, array_map(static fn (Media $media): ?string => $media->getFilename(), $medias));
    }

    // A site declaring no photograph for that product still gets a slider, from the generic pool - every site starts with no keyed image at all
    public function testTheSliderFallsBackOnTheGenericPoolWhenTheProductHasNoPhotograph(): void
    {
        $this->createProvider()->getShowcases();

        $this->assertCount(3, $this->contextOf('@c975LUi/components/Slider/Slider.html.twig')['media']);
    }

    private function contextOf(string $template): array
    {
        foreach ($this->rendered as $render) {
            if ($render['template'] === $template) {
                return $render['context'];
            }
        }

        $this->fail($template . ' was never rendered');
    }

    /**
     * @param list<string>                $images
     * @param array<string, list<string>> $keyed
     */
    private function createProvider(array $images = ['medias/placeholder-1.jpg', 'medias/placeholder-2.jpg', 'medias/placeholder-3.jpg'], array $keyed = []): ShopShowcaseProvider
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context = []): string {
            $this->rendered[] = ['template' => $template, 'context' => $context];

            return '<div>' . $template . '</div>';
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn($images);

        $attacher = $this->createStub(BlockFixtureMediaAttacher::class);
        $attacher->method('nextPlaceholderImage')->willReturnCallback(fn (): Media => new Media());
        $attacher->method('placeholderImagesFor')->willReturnCallback(
            static fn (string $key): array => array_map(
                static fn (string $filename): Media => new Media()->setFilename($filename),
                $keyed[str_replace('shop/', '', $key)] ?? [],
            ),
        );

        return new ShopShowcaseProvider($twig, $translator, $registry, $attacher, new ShopSampleCatalog());
    }
}
