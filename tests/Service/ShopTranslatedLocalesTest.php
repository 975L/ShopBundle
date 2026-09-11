<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ShopTranslatedLocales;
use PHPUnit\Framework\TestCase;

class ShopTranslatedLocalesTest extends TestCase
{
    // Everything on the index but the products' own names is this bundle's interface, which ships as a catalogue per language
    public function testTheIndexSaysSomethingInEveryLanguageTheSiteDeclares(): void
    {
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forShop());
    }

    // A product sheet answers in every language the site declares: "/en" is the language the shop is read in, not a claim about the row - a name still in French under an English interface is a page half translated, not another page
    public function testAProductSheetAnswersInEveryLanguageTheSiteDeclares(): void
    {
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forProduct(new Product()));
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forCategory(new ProductCategory()));
    }

    private function locales(): ShopTranslatedLocales
    {
        return new ShopTranslatedLocales(new SiteLocales(['fr', 'en', 'es'], 'fr'));
    }
}
