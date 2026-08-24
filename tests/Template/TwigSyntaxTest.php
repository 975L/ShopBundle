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

// A template is compiled by the first request that renders it and by nothing here, so a syntax dropped by Twig 3 goes unnoticed until a page is opened - and a block kind is opened on a consuming site, never in this repository
class TwigSyntaxTest extends TestCase
{
    // "{% for x in y if condition %}" was dropped in Twig 3: it is a SyntaxError now, not a deprecation, and "|filter()" is what replaces it
    public function testNoTemplateUsesTheForIfModifierTwigDropped(): void
    {
        foreach ($this->templates() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '/\{%-?\s*for\s+.+?\s+if\s+.+?-?%\}/',
                $source,
                sprintf('"%s" filters a loop with the "for ... if" modifier Twig 3 removed - use "|filter(item => ...)".', $path)
            );
        }
    }

    // The one loop of this bundle that filters what it walks
    public function testTheGiftCardsBlockFiltersItsAmountsWithTheFilterFunction(): void
    {
        $this->assertStringContainsString(
            '{% for item in product.publishedItems|filter(item => item.giftCard) %}',
            (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/blocks/GiftCards.html.twig')
        );
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';
        $templates = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getPathname(), '.twig')) {
                $templates[substr($file->getPathname(), \strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }

        $this->assertNotEmpty($templates);

        return $templates;
    }
}
