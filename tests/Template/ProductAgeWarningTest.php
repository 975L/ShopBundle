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

// The sheet hands the fragment the age the product declares, and the fragment decides whether there is anything to say - a shop has no language of its own to ask the sentence in, so the visitor's own is used. Nothing renders here, so the call is read where it is written
class ProductAgeWarningTest extends TestCase
{
    private const string SHEET = 'templates/product/display.html.twig';

    // A shop selling anything to anyone installs the very same bundle: the age is what tells a sheet that says nothing from one opening on a warning, and it is the fragment's own guard
    public function testTheSheetHandsTheProductsAgeToTheFragment(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Alert:AgeWarning age="{{ product.age }}"/>', $this->read(self::SHEET));
    }

    // The very field the graph builds its audience from (see ProductSnippetBuilder::audience()), so what a search engine reads and what the visitor reads can never disagree
    public function testItReadsTheFieldTheStructuredDataReads(): void
    {
        $this->assertStringContainsString('getAge()', $this->read('src/Service/ProductSnippetBuilder.php'));
    }

    // The sentence and the "has the site written one" question are the fragment's own business - reading the setting here too would be the same guard written in two places
    public function testTheSheetDoesNotReadTheSettingItself(): void
    {
        $this->assertStringNotContainsString("config('site-age-warning')", $this->read(self::SHEET));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
