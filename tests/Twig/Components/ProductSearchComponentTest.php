<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig\Components;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\ShopBundle\Twig\Components\ProductSearchComponent;
use PHPUnit\Framework\TestCase;

// The instant search answers in the language being rendered, like every listing of the shop
class ProductSearchComponentTest extends TestCase
{
    // The products found are translated before they are drawn
    public function testTheProductsFoundAreTranslated(): void
    {
        $found = [new Product()->setTitle('item-1')];

        $productService = $this->createStub(ProductServiceInterface::class);
        $productService->method('search')->willReturn($found);

        $translator = $this->createMock(ShopTranslator::class);
        $translator->expects($this->once())->method('apply')->with($found);

        $component = new ProductSearchComponent($productService, $translator);
        $component->query = 'item';

        $this->assertSame($found, $component->getProducts());
    }

    // Nothing typed, nothing searched
    public function testAnEmptyQuerySearchesNothing(): void
    {
        $translator = $this->createMock(ShopTranslator::class);
        $translator->expects($this->never())->method('apply');

        $component = new ProductSearchComponent($this->createStub(ProductServiceInterface::class), $translator);
        $component->query = '  ';

        $this->assertNull($component->getProducts());
    }
}
