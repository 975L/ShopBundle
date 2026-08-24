<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Management\ShopBlockEditUrlProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class ShopBlockEditUrlProviderTest extends TestCase
{
    // The three owners of this bundle's blocks, each answering with whatever the test handed it
    private function createProvider(array $productsOwningBlocks, array $categoriesOwningBlocks = [], array $settingsOwningBlocks = []): ShopBlockEditUrlProvider
    {
        return new ShopBlockEditUrlProvider(
            $this->createRepository(ProductRepository::class, $productsOwningBlocks),
            $this->createRepository(ProductCategoryRepository::class, $categoriesOwningBlocks),
            $this->createRepository(ShopSettingsRepository::class, $settingsOwningBlocks),
            $this->createAdminUrlGenerator(),
        );
    }

    private function createRepository(string $class, array $ownersOwningBlocks): object
    {
        $repository = $this->createStub($class);
        $repository->method('findByBlockIds')->willReturn($ownersOwningBlocks);

        return $repository;
    }

    // Every setter returns the generator itself (BlockFocusUrl chains them), and generateUrl() echoes back whatever focusBlock was set - what matters here is which block the button points at
    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $focusBlock = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnCallback(function (string $key, mixed $value) use ($generator, &$focusBlock) {
            $focusBlock = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use (&$focusBlock): string {
            return '/admin/product?focusBlock=' . $focusBlock;
        });

        return $generator;
    }

    private function blockWithId(int $id): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    private function productWithBlocks(Block ...$blocks): Product
    {
        $product = new Product();
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 7);
        foreach ($blocks as $block) {
            $product->addBlock($block);
        }

        return $product;
    }

    private function categoryWithBlocks(Block ...$blocks): ProductCategory
    {
        $category = new ProductCategory();
        new \ReflectionProperty(ProductCategory::class, 'id')->setValue($category, 3);
        foreach ($blocks as $block) {
            $category->addBlock($block);
        }

        return $category;
    }

    private function settingsWithBlocks(Block ...$blocks): ShopSettings
    {
        $settings = new ShopSettings();
        new \ReflectionProperty(ShopSettings::class, 'id')->setValue($settings, 1);
        foreach ($blocks as $block) {
            $settings->addBlock($block);
        }

        return $settings;
    }

    // A block composed on a sheet resolves to its product's edit screen, focused on that very block's row
    public function testGetEditUrlsResolvesUrlForBlockOwnedByProduct(): void
    {
        $block = $this->blockWithId(10);

        $provider = $this->createProvider([$this->productWithBlocks($block)]);

        $this->assertSame([10 => '/admin/product?focusBlock=10'], $provider->getEditUrls([$block]));
    }

    // Only the hovered blocks are answered for, not every block the same sheet holds
    public function testGetEditUrlsAnswersOnlyForRequestedBlocks(): void
    {
        $requested = $this->blockWithId(30);
        $other = $this->blockWithId(31);

        $provider = $this->createProvider([$this->productWithBlocks($requested, $other)]);

        $this->assertSame([30 => '/admin/product?focusBlock=30'], $provider->getEditUrls([$requested]));
    }

    // A block no product owns (a Page's own block, which SiteBundle answers for) resolves to nothing - no error
    public function testGetEditUrlsReturnsEmptyArrayForUnownedBlock(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([$this->blockWithId(40)]));
    }

    // Passing no blocks skips the repository query entirely
    public function testGetEditUrlsReturnsEmptyArrayForNoBlocks(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([]));
    }

    // A block composed on a category page resolves to that category's edit screen, the same way a sheet's does
    public function testGetEditUrlsResolvesUrlForBlockOwnedByCategory(): void
    {
        $block = $this->blockWithId(50);

        $provider = $this->createProvider([], [$this->categoryWithBlocks($block)]);

        $this->assertSame([50 => '/admin/product?focusBlock=50'], $provider->getEditUrls([$block]));
    }

    // A block composed on the shop's index resolves to the single row holding it
    public function testGetEditUrlsResolvesUrlForBlockOwnedByShopSettings(): void
    {
        $block = $this->blockWithId(60);

        $provider = $this->createProvider([], [], [$this->settingsWithBlocks($block)]);

        $this->assertSame([60 => '/admin/product?focusBlock=60'], $provider->getEditUrls([$block]));
    }

    // The three owners answer together, each block pointing at the screen composing it
    public function testGetEditUrlsResolvesBlocksOfEveryOwnerAtOnce(): void
    {
        $productBlock = $this->blockWithId(70);
        $categoryBlock = $this->blockWithId(71);
        $settingsBlock = $this->blockWithId(72);

        $provider = $this->createProvider(
            [$this->productWithBlocks($productBlock)],
            [$this->categoryWithBlocks($categoryBlock)],
            [$this->settingsWithBlocks($settingsBlock)],
        );

        $this->assertSame(
            [
                70 => '/admin/product?focusBlock=70',
                71 => '/admin/product?focusBlock=71',
                72 => '/admin/product?focusBlock=72',
            ],
            $provider->getEditUrls([$productBlock, $categoryBlock, $settingsBlock]),
        );
    }
}
