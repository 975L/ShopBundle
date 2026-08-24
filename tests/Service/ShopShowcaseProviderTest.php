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

    // One physical item and one downloaded one, the two a real sheet tells apart by the file name alone
    public function testTheItemsShowAPostedOneAndADownloadedOne(): void
    {
        $this->createProvider()->getShowcases();

        $items = $this->contextOf('@c975LShop/components/Product/Items.html.twig')['items'];
        $this->assertNull($items[0]->getFile()?->getName());
        $this->assertSame('exemple.pdf', $items[1]->getFile()->getName());
    }

    // The slider reads its images with vich_uploader_asset(), which needs the entity rather than a path
    public function testTheSliderIsHandedTheMediaEntitiesTheAttacherBuilds(): void
    {
        $this->createProvider()->getShowcases();

        $this->assertContainsOnlyInstancesOf(Media::class, $this->contextOf('@c975LUi/components/Slider/Slider.html.twig')['media']);
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

    private function createProvider(array $images = ['medias/placeholder-1.jpg', 'medias/placeholder-2.jpg', 'medias/placeholder-3.jpg']): ShopShowcaseProvider
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

        return new ShopShowcaseProvider($twig, $translator, $registry, $attacher);
    }
}
