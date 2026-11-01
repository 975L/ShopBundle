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
use Symfony\Component\Finder\Finder;

// What an editor writes reaches a visitor's page through the sanitizer, never as it was typed
class RichTextEscapingTest extends TestCase
{
    // What may stay raw: the JSON-LD, hex-encoded by JSON_HEX_TAG before it gets here
    private const array RAW_ALLOWED = ['productsJsonLd|raw', 'breadcrumbJsonLd|raw', 'jsonLd|raw'];

    public function testNoTemplatePrintsAnythingElseRaw(): void
    {
        $raw = [];
        foreach (new Finder()->files()->in(\dirname(__DIR__, 2) . '/templates')->name('*.twig') as $file) {
            $source = (string) preg_replace('/\{#.*?#\}/s', '', $file->getContents());
            preg_match_all('/[\w.\']+(?:\([^)]*\))?(?:\|\w+(?:\([^)]*\))?)*\|raw\b/', $source, $matches);
            foreach (array_diff($matches[0], self::RAW_ALLOWED) as $expression) {
                $raw[] = $file->getRelativePathname() . ': ' . $expression;
            }
        }

        $this->assertSame([], $raw);
    }

    // Under the sanitizer CoreBundle declares
    public function testTheRichFieldsGoThroughTheSanitizer(): void
    {
        $this->assertStringContainsString("category.description|sanitize_html('c975l.rich')", $this->read('category/display.html.twig'));
        $this->assertStringContainsString("description|default('')|sanitize_html('c975l.rich')", $this->read('components/Product/Description.html.twig'));
        $this->assertStringContainsString("label|default('')|sanitize_html('c975l.rich')", $this->read('components/Button/Button.html.twig'));
    }

    private function read(string $template): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);
    }
}
