<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Template;

use PHPUnit\Framework\TestCase;

// A product's medias rarely carry an alternative text of their own, so the slider is told what they show: without it every gallery image publishes an empty alt. Nothing renders here, so the call is read where it is written
class ProductGalleryAltTest extends TestCase
{
    // The sheet's own gallery, written when no slider block took it over
    public function testTheSheetGalleryFallsBackOnTheProductTitle(): void
    {
        $this->assertStringContainsString('fallbackAlt="{{ product.title }}"', $this->read('templates/product/display.html.twig'));
    }

    // The slider block placed on a page shows the same medias, so it says the same thing about them
    public function testTheSliderBlockFallsBackOnTheProductTitle(): void
    {
        $this->assertStringContainsString('fallbackAlt="{{ product.title }}"', $this->read('templates/blocks/ProductSlider.html.twig'));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
