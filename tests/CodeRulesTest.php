<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules (see ConfigBundle's CodeRulesTestCase) replayed on this bundle's code
class CodeRulesTest extends CodeRulesTestCase
{
    // Where the bundle's classes live
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    // The namespace those classes are read under
    protected function sourceNamespace(): string
    {
        return 'c975L\ShopBundle';
    }

    // The bulk queries whose cache is dropped elsewhere, or that touch rows no cached fragment reads
    protected function allowed(): array
    {
        return [
            self::BULK_QUERY => [
                'c975L\ShopBundle\Repository\ProductItemRepository::claimOrdered' => 'Its caller dates the item (ProductBasketItemProvider::countOrdered()), the flush that follows invalidating the cached cards',
                'c975L\ShopBundle\Repository\ProductItemRepository::addOrdered' => 'Its caller dates the item (ProductBasketItemProvider::countOrdered()), the flush that follows invalidating the cached cards',
                'c975L\ShopBundle\Repository\ProductItemDownloadRepository::deleteExpiredBefore' => 'Download links are read live by the download route and the customer area, never cached',
                'c975L\ShopBundle\Repository\ProductItemDownloadRepository::expireByBasket' => 'Download links are read live by the download route and the customer area, never cached',
                'c975L\ShopBundle\Repository\ProductItemStockAlertRepository::deleteByEmail' => 'Stock alerts are read by their own uncached page and by the sending command, never by a cached fragment',
            ],
        ];
    }
}
