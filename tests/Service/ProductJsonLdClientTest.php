<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Service\ProductJsonLdClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// What the health check actually reads off a served sheet: how many JSON-LD blocks it carries, how many of them do not parse, and the types of those that do
class ProductJsonLdClientTest extends TestCase
{
    public function testAPageCarryingNoStructuredDataReportsNothing(): void
    {
        $this->assertSame(
            ['blocks' => 0, 'invalid' => 0, 'types' => []],
            $this->read('<html><body><h1>A product</h1></body></html>')
        );
    }

    public function testTheTypeOfAPlainNodeIsRead(): void
    {
        $found = $this->read($this->script('{"@context":"https://schema.org","@type":"Product","name":"A poster"}'));

        $this->assertSame(1, $found['blocks']);
        $this->assertSame(0, $found['invalid']);
        $this->assertSame(['Product'], $found['types']);
    }

    // The three shapes a valid document may take, each one holding its types somewhere else
    public function testTheTypesOfAGraphAndOfAPlainListAreReadToo(): void
    {
        $found = $this->read(
            $this->script('{"@graph":[{"@type":"BreadcrumbList"},{"@type":"Product"}]}')
            . $this->script('[{"@type":"Organization"},{"@type":"WebSite"}]')
        );

        $this->assertSame(2, $found['blocks']);
        $this->assertSame(['BreadcrumbList', 'Product', 'Organization', 'WebSite'], $found['types']);
    }

    // A node naming several types at once, which schema.org allows
    public function testANodeNamingSeveralTypesCarriesThemAll(): void
    {
        $found = $this->read($this->script('{"@type":["Product","IndividualProduct"]}'));

        $this->assertSame(['Product', 'IndividualProduct'], $found['types']);
    }

    // The defect nobody reading the page can see: markup is served, and no search engine can use it
    public function testABlockThatDoesNotParseIsCountedAsInvalid(): void
    {
        $found = $this->read($this->script('{"@type":"Product",}') . $this->script('{"@type":"BreadcrumbList"}'));

        $this->assertSame(2, $found['blocks']);
        $this->assertSame(1, $found['invalid']);
        $this->assertSame(['BreadcrumbList'], $found['types']);
    }

    // The same type served by two blocks is one type to report, not two
    public function testATypeServedTwiceIsReportedOnce(): void
    {
        $found = $this->read($this->script('{"@type":"Product"}') . $this->script('{"@type":"Product"}'));

        $this->assertSame(['Product'], $found['types']);
    }

    // A single-quoted attribute and anything else on the tag, both of which a template may write
    public function testTheScriptIsFoundWhateverItsAttributesLookLike(): void
    {
        $found = $this->read("<script id='ld' type='application/ld+json' data-turbo='false'>{\"@type\":\"Product\"}</script>");

        $this->assertSame(1, $found['blocks']);
        $this->assertSame(['Product'], $found['types']);
    }

    private function script(string $json): string
    {
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private function read(string $html): array
    {
        return new ProductJsonLdClient(new MockHttpClient(new MockResponse($html)))->readStructuredData('https://example.com/shop/products/a-poster');
    }
}
