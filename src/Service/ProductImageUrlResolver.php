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
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\UiBundle\Service\ImageDimensionsReader;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UrlHelper;

// A product's pictures as absolute urls, each at the largest size kept on disk - what the merchant feed publishes for each of its items, an item's own picture staying small for the row that shows it (see ProductItemMedia::getImageWidth()) where Merchant Center refuses anything under 500 x 500 pixels
class ProductImageUrlResolver
{
    public function __construct(
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper,
        private readonly ImageDimensionsReader $imageDimensionsReader,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // The product's first picture, the one its sheet shares (see ShopBlockExtension::getSheetData())
    public function forProduct(Product $product): ?string
    {
        return $this->allForProduct($product)[0] ?? null;
    }

    // Every picture of the product in its gallery order, a media without a file left out
    /** @return list<string> */
    public function allForProduct(Product $product): array
    {
        $urls = [];
        foreach ($product->getMedias() as $media) {
            $name = $this->largestFilename($media);
            if (null !== $name) {
                $urls[] = $this->urlHelper->getAbsoluteUrl($this->packages->getUrl($name));
            }
        }

        return $urls;
    }

    // The "-highres.webp" sibling UiBundle saves beside the stored file, whatever its extension, when it is on disk and wider, the stored file otherwise - a picture uploaded before the derivative was asked for has none
    private function largestFilename(ProductMedia $media): ?string
    {
        $name = $media->getName();
        if (null === $name) {
            return null;
        }

        $highres = preg_replace('/\.[^.\/]+$/', '', $name) . '-highres.webp';
        $dimensions = $this->imageDimensionsReader->read($this->projectDir . '/public/' . ltrim($highres, '/'));

        return null !== $dimensions && $dimensions['width'] > $media->getImageWidth() ? $highres : $name;
    }
}
