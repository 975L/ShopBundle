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

// The listing grows through UiBundle's infiniteScroll controller, which finds the cards and the next link by those attributes alone - in the fetched page as well as in this one, where Stimulus has connected nothing. Nothing renders the templates here, so the contract is read where it is written
class ShopListingInfiniteScrollTest extends TestCase
{
    private const string INDEX = 'templates/shop/index.html.twig';
    private const string PRODUCTS = 'templates/components/Product/Products.html.twig';

    public function testTheListingMountsTheController(): void
    {
        $this->assertStringContainsString('data-controller="infiniteScroll"', $this->read(self::INDEX));
    }

    // The cards go in the list the controller appends to, and the list is only marked where the listing asked for it
    public function testTheCardsAreTheAppendedList(): void
    {
        $this->assertStringContainsString('data-infiniteScroll-target="list"', $this->read(self::PRODUCTS));
        $this->assertStringContainsString('{% if infinite|default(false) %}', $this->read(self::PRODUCTS));
        $this->assertStringContainsString('infinite="true"', $this->read(self::INDEX));
    }

    // Both ends of the link: the one the controller fetches and replaces, and the one a visitor without javascript follows
    public function testTheNextPageIsAnOrdinaryLink(): void
    {
        $index = $this->read(self::INDEX);

        $this->assertStringContainsString('data-infiniteScroll-target="next"', $index);
        $this->assertStringContainsString('data-action="click->infiniteScroll#load"', $index);
        $this->assertStringContainsString("path(products.route, products.query({'p': products.getCurrentPageNumber + 1}))", $index);
    }

    // The count is what the footer says of the listing, and it grows with it
    public function testTheCountIsUpdatedWithTheListing(): void
    {
        $this->assertStringContainsString('data-infiniteScroll-target="count"', $this->read(self::INDEX));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
