<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;

// The catalog's front page is the only url of this bundle no row describes: a product and a category each say their own from their columns, and a UrlMetadata row would never be read for them
class UrlMetadataProvider implements UrlMetadataProviderInterface
{
    public function getUrlMetadataPaths(): array
    {
        return ['/shop'];
    }
}
