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

// The shop's layout drops the wrapper an app puts around a page's content and leaves the measure to each template, so that a block composed on a shop page carries the one gutter it already has. Nothing renders the templates here, so the contract is read where it is written
class ShopPageMeasureTest extends TestCase
{
    private const string LAYOUT = 'templates/layout.html.twig';

    // The templates extending the layout, each of them stating its own measure
    private const array PAGES = [
        'templates/shop/index.html.twig',
        'templates/category/display.html.twig',
        'templates/product/display.html.twig',
        'templates/shop/terms_of_sales.html.twig',
        'templates/stock_alert/new.html.twig',
        'templates/stock_alert/unsubscribed.html.twig',
        'templates/product/item_downloaded.html.twig',
    ];

    // Overriding "container" is what drops the app's wrapper, and the block is left bare so no page is measured twice
    public function testTheLayoutOverridesTheContainerAndWrapsNothing(): void
    {
        $layout = $this->markup(self::LAYOUT);

        $this->assertStringContainsString('{% block container %}', $layout);
        $this->assertStringNotContainsString('section-wrap', $layout);
        $this->assertStringNotContainsString('parent()', $layout);
    }

    public function testEveryPageExtendsTheLayoutAndStatesItsOwnMeasure(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->read($page);

            $this->assertStringContainsString("{% extends '@c975LShop/layout.html.twig' %}", $source, $page . ' does not extend the shop layout');
            $this->assertStringContainsString('class="section-wrap"', $source, $page . ' states no measure of its own');
        }
    }

    // A block carries its own ".section-wrap", so the two pages composing blocks beside their own content render them outside of theirs - the product sheet excepted, its blocks sitting in the grid column beside the gallery
    public function testTheComposedBlocksAreRenderedBare(): void
    {
        foreach (['templates/shop/index.html.twig', 'templates/category/display.html.twig'] as $page) {
            $source = $this->read($page);
            $blocks = strpos($source, '<twig:c975LUi:Blocks:Blocks');
            $this->assertIsInt($blocks, $page . ' composes no block');

            // The wrapper opened last before the blocks has to be closed before them
            $before = substr($source, 0, $blocks);
            $wrap = strrpos($before, '<div class="section-wrap">');

            $this->assertIsInt($wrap, $page . ' states no measure above its blocks');
            $this->assertStringContainsString('</div>', substr($before, $wrap), $page . ' renders its blocks inside a section-wrap');
        }
    }

    // A shop block hangs straight under UiBundle's ".blocks", which is "display: contents", so nothing above it measures it: each of them states the measure and the vertical step for itself, as every UiBundle kind does
    public function testEveryBlockStatesItsOwnMeasure(): void
    {
        // The listing writes its own frame through the component (prop "framed"), and the slider is laid out on --reading-max-width by UiBundle's own sass
        $exceptions = ['Products.html.twig', 'ProductSlider.html.twig'];

        foreach (glob(\dirname(__DIR__, 2) . '/templates/blocks/*.html.twig') as $path) {
            if (\in_array(basename($path), $exceptions, true)) {
                continue;
            }

            $source = (string) file_get_contents($path);
            $this->assertStringContainsString('class="block-section"', $source, basename($path) . ' takes no vertical step of its own');
            $this->assertStringContainsString('class="section-wrap"', $source, basename($path) . ' states no measure of its own');
        }
    }

    // The listing is framed by the component, so the "see more" button beside it is the one thing the block still has to measure
    public function testTheProductsBlockFramesItsListingAndItsButton(): void
    {
        $source = $this->markup('templates/blocks/Products.html.twig');

        $this->assertStringContainsString('framed="true"', $source);
        $this->assertStringContainsString('class="block-section"', $source);
        $this->assertStringContainsString('class="section-wrap"', $source);
    }

    // The comments of these templates name what the markup must not do, so they are dropped before it is read
    private function markup(string $relativePath): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $this->read($relativePath));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
