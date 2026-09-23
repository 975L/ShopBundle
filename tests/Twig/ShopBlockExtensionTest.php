<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductRecommendationServiceInterface;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\ShopBundle\Twig\Extension\ShopBlockExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\RequestStack;

// What the block templates of this bundle resolve at render time: the kinds a sheet holds, which is what tells its hardcoded sections to step aside, and the visuals a card is bought on
class ShopBlockExtensionTest extends TestCase
{
    private ShopBlockExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ShopBlockExtension(
            $this->createStub(ProductRepository::class),
            $this->createStub(ProductCategoryRepository::class),
            $this->createStub(ProductRecommendationServiceInterface::class),
            new RequestStack(),
            // A double rather than the real one: what it lays over is ShopTranslator's own test, and a single-language site lays nothing
            $this->createStub(ShopTranslator::class),
            new TagAwareAdapter(new ArrayAdapter()),
        );
    }

    public function testASheetNamesItsOwnKinds(): void
    {
        $this->assertSame(['hero', 'shop_product_items'], $this->extension->getSheetKinds([$this->block('hero'), $this->block('shop_product_items')]));
    }

    // A buy table dropped in a column of a "flex_columns" is still on the sheet, and the section it takes over still has to step aside
    public function testTheSlotsOfAContainerAreNamedToo(): void
    {
        $column = $this->block('flex_column');
        $column->addSlot($this->block('shop_product_items'));

        $row = $this->block('flex_columns');
        $row->addSlot($column);

        $this->assertSame(['flex_columns', 'flex_column', 'shop_product_items'], $this->extension->getSheetKinds([$row]));
    }

    public function testAKindHeldTwiceIsNamedOnce(): void
    {
        $this->assertSame(['card'], $this->extension->getSheetKinds([$this->block('card'), $this->block('card')]));
    }

    // The gift cards block shows the visuals and nothing else: an ordinary product of the same catalogue has no amount to be bought for
    public function testOnlyTheProductsCarryingAnAmountAreOfferedAsCards(): void
    {
        $extension = $this->extensionHolding([
            $this->product('affiche', null),
            $this->product('carte-noel', 5000),
            $this->product('carte-anniversaire', 2000),
        ]);

        $slugs = array_map(static fn (Product $product): ?string => $product->getSlug(), $extension->getGiftCards());

        $this->assertSame(['carte-noel', 'carte-anniversaire'], $slugs);
    }

    // Same cap as every other listing of this bundle, and applied after the filtering: a maximum of one must not answer nothing because the first product of the catalogue is not a card
    public function testTheListingStopsAtTheMaximumItWasGiven(): void
    {
        $extension = $this->extensionHolding([
            $this->product('affiche', null),
            $this->product('carte-noel', 5000),
            $this->product('carte-anniversaire', 2000),
        ]);

        $this->assertCount(1, $extension->getGiftCards(1));
    }

    public function testAShopSellingNoCardOffersNone(): void
    {
        $this->assertSame([], $this->extensionHolding([$this->product('affiche', null)])->getGiftCards());
    }

    /** @param list<Product> $products */
    private function extensionHolding(array $products): ShopBlockExtension
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findAllSorted')->willReturn($products);

        return new ShopBlockExtension(
            $repository,
            $this->createStub(ProductCategoryRepository::class),
            $this->createStub(ProductRecommendationServiceInterface::class),
            new RequestStack(),
            $this->createStub(ShopTranslator::class),
            new TagAwareAdapter(new ArrayAdapter()),
        );
    }

    private function product(string $slug, ?int $giftCardValue): Product
    {
        return new Product()
            ->setTitle($slug)
            ->setSlug($slug)
            ->addItem(new ProductItem()->setTitle($slug)->setSlug($slug)->setGiftCardValue($giftCardValue))
        ;
    }

    private function block(string $kind): Block
    {
        return new Block()->setKind($kind);
    }

    // Read once per product and served from the cache afterwards: a hit loads neither the medias nor the blocks
    public function testTheSheetDataIsReadOnceUntilTheProductsAreEmptied(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = new ShopBlockExtension(
            $this->createStub(ProductRepository::class),
            $this->createStub(ProductCategoryRepository::class),
            $this->createStub(ProductRecommendationServiceInterface::class),
            new RequestStack(),
            $this->createStub(ShopTranslator::class),
            $cache,
        );

        $product = new Product();
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 5);
        $product->addBlock(new Block()->setKind('shop_product_items'));

        $this->assertSame(['shop_product_items'], $extension->getSheetData($product)['sheetKinds']);

        $product->addBlock(new Block()->setKind('shop_recommendations'));
        $this->assertSame(['shop_product_items'], $extension->getSheetData($product)['sheetKinds']);

        $cache->invalidateTags(['shop_products']);
        $this->assertSame(['shop_product_items', 'shop_recommendations'], $extension->getSheetData($product)['sheetKinds']);
        $this->assertNull($extension->getSheetData($product)['ogImage']);
    }

    // A slot added to one of the sheet's containers reaches the entry through that container's own tag, which neither the product nor its run of blocks reports
    public function testTheSheetDataIsEmptiedByItsContainersTag(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = new ShopBlockExtension(
            $this->createStub(ProductRepository::class),
            $this->createStub(ProductCategoryRepository::class),
            $this->createStub(ProductRecommendationServiceInterface::class),
            new RequestStack(),
            $this->createStub(ShopTranslator::class),
            $cache,
        );

        $product = new Product();
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 5);
        $container = new Block()->setKind('flex_columns');
        new \ReflectionProperty(Block::class, 'id')->setValue($container, 12);
        $product->addBlock($container);

        $this->assertSame(['flex_columns'], $extension->getSheetData($product)['sheetKinds']);

        $container->addSlot(new Block()->setKind('shop_product_items'));
        $cache->invalidateTags(['block_12']);
        $this->assertSame(['flex_columns', 'shop_product_items'], $extension->getSheetData($product)['sheetKinds']);
    }
}
