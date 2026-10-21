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

// A product's pictures as absolute urls - what its sheet's structured data and the merchant feed both publish, so the two never point at different files
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

        return null === $media?->getName() ? null : $this->absolute($media->getName());
    }

    // Every visible item's picture, keyed by its slug - the asset() the item's card applies to the same media
    /** @return array<string, string> */
    public function forItems(Product $product): array
    {
        $urls = [];

        foreach ($product->getVisibleItems() as $item) {
            $media = $item->getMedia();
            if (null !== $media && null !== $media->getName()) {
                $urls[trim((string) $item->getSlug())] = $this->absolute($media->getName());
            }
        }

        return $urls;
    }

    // A media's public path made absolute, the way the sheet's own image is
    private function absolute(string $name): string
    {
        return $this->urlHelper->getAbsoluteUrl($this->packages->getUrl($name));
    }
}
