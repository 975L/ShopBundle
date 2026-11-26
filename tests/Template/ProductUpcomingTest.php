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

// A product dated ahead shows its title and its day only, read where the controller and the page are written
class ProductUpcomingTest extends TestCase
{
    private const string PAGE = 'templates/product/upcoming.html.twig';
    private const string CONTROLLER = 'src/Controller/ProductController.php';

    // The public sheet of a product not out yet is the short page, the preview keeping the whole sheet for its editors
    public function testTheSheetOfAProductNotOutIsTheShortPage(): void
    {
        $controller = $this->read(self::CONTROLLER);

        $this->assertStringContainsString("if (!\$product->isReleased()) {\n            return \$this->negotiator->vary(\$request, \$this->render('@c975LShop/product/upcoming.html.twig'", $controller);
        $this->assertSame(1, substr_count($controller, 'upcoming.html.twig'));
    }

    // Its title and its day, nothing of its texts nor its pictures, kept out of search engines
    public function testThePageShowsOnlyTheTitleAndTheDay(): void
    {
        $page = $this->read(self::PAGE);

        $this->assertStringContainsString('product.availableAt', $page);
        $this->assertStringContainsString("{% set robots = 'noindex, follow' %}", $page);
        foreach (['medias', 'description', 'ogImage', 'Slider', 'Blocks'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $page);
        }
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
