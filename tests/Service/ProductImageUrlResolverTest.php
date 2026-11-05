<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Service\ProductImageUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;

// The picture the feed publishes for a product's items, made absolute
class ProductImageUrlResolverTest extends TestCase
{
    // The product's first picture, and nothing for a product holding none
    public function testForProductIsTheFirstPictureMadeAbsolute(): void
    {
        $product = new Product()->setTitle('Le Loup')->setSlug('le-loup');
        $this->assertNull($this->resolver()->forProduct($product));

        $product->addMedia(new ProductMedia()->setName('medias/loup.webp'));
        $product->addMedia(new ProductMedia()->setName('medias/loup-dos.webp'));

        $this->assertSame('https://example.org/medias/loup.webp', $this->resolver()->forProduct($product));
    }

    private function resolver(): ProductImageUrlResolver
    {
        return new ProductImageUrlResolver(
            new Packages(new PathPackage('/', new EmptyVersionStrategy())),
            new UrlHelper(new RequestStack([Request::create('https://example.org/shop')])),
        );
    }
}
