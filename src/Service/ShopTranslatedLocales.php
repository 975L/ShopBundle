<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;

// Which languages each screen of the shop really says something in - the one thing every localised url of this bundle is gated on (see LocalizedRouteNegotiator). The whole answer lives here rather than in three controllers, so translating the catalogue is a change to this file and to nothing else
class ShopTranslatedLocales
{
    public function __construct(private readonly SiteLocales $siteLocales)
    {
    }

    // The index says the same thing in every language the site declares: everything on it but the products' own names is the bundle's own interface, which ships as a catalogue per language
    /** @return list<string> */
    public function forShop(): array
    {
        return $this->siteLocales->all();
    }

    // A product sheet answers in every language the site declares, translated or not: "/en" is the language the shop is read in and not a claim about the row, a name still in the writing language being a page half translated rather than another page - what a row really says is ShopTranslator::translatedLocales(), which an hreflang group may name
    /** @return list<string> */
    public function forProduct(Product $product): array
    {
        return $this->siteLocales->all();
    }

    /** @return list<string> */
    public function forCategory(ProductCategory $category): array
    {
        return $this->siteLocales->all();
    }
}
