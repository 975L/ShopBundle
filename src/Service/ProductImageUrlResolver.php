<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Product;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;

// A product's picture as an absolute url - what the merchant feed publishes for each of its items, an item's own picture staying small for the row that shows it (see ProductItemMedia::getImageWidth()) where Merchant Center refuses anything under 500 x 500 pixels
class ProductImageUrlResolver
{
    public function __construct(
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    // The product's first picture, the one its sheet shares (see ShopBlockExtension::getSheetData())
    public function forProduct(Product $product): ?string
    {
        $media = $product->getMedias()->first() ?: null;

        return null === $media?->getName() ? null : $this->urlHelper->getAbsoluteUrl($this->packages->getUrl($media->getName()));
    }
}
