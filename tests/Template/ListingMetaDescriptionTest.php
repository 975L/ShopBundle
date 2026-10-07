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

// The shop's index and a category page must never publish an empty or generic meta description. Nothing renders here, so the fallback is read where it is written
class ListingMetaDescriptionTest extends TestCase
{
    // The shop's index falls back on a sentence naming the site, behind the editor's own row
    public function testTheShopIndexFallsBackOnASentenceNamingTheSite(): void
    {
        $template = $this->read('templates/shop/index.html.twig');

        $this->assertStringContainsString("url_metadata_summary('text.meta_shop'|trans({'%site%': config('site-name')}))", $template);
        $this->assertStringNotContainsString('label.all_our_items', $template);
    }

    // A category keeps its own description, and names itself and the site when it has none
    public function testACategoryWithoutDescriptionFallsBackOnASentenceNamingIt(): void
    {
        $template = $this->read('templates/category/display.html.twig');

        $this->assertStringContainsString('category.description|striptags|trim is not empty ? category.description', $template);
        $this->assertStringContainsString("'text.meta_category'|trans({'%category%': category.name, '%site%': config('site-name')})", $template);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
