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
use c975L\UiBundle\Service\ImageDimensionsReader;
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

    // Every picture in its order, a media whose file is gone left out
    public function testAllForProductListsEveryPictureMadeAbsolute(): void
    {
        $product = new Product()->setTitle('Le Loup')->setSlug('le-loup');
        $this->assertSame([], $this->resolver()->allForProduct($product));

        $product->addMedia(new ProductMedia());
        $product->addMedia(new ProductMedia()->setName('medias/loup.webp'));
        $product->addMedia(new ProductMedia()->setName('medias/loup-dos.webp'));

        $this->assertSame(['https://example.org/medias/loup.webp', 'https://example.org/medias/loup-dos.webp'], $this->resolver()->allForProduct($product));
        $this->assertSame('https://example.org/medias/loup.webp', $this->resolver()->forProduct($product));
    }

    // The highres sibling, always a webp, stands for a picture when it is on disk and wider than the stored file, the stored file otherwise
    public function testTheHighresSiblingIsPreferredWhenWider(): void
    {
        $projectDir = sys_get_temp_dir() . '/c975l-shop-' . uniqid();
        mkdir($projectDir . '/public/medias', 0o777, true);
        imagewebp(imagecreatetruecolor(1600, 1600), $projectDir . '/public/medias/loup-highres.webp');
        imagewebp(imagecreatetruecolor(600, 600), $projectDir . '/public/medias/ours-highres.webp');
        imagewebp(imagecreatetruecolor(1200, 1200), $projectDir . '/public/medias/lynx-highres.webp');

        $product = new Product()->setTitle('Le Loup')->setSlug('le-loup');
        $product->addMedia(new ProductMedia()->setName('medias/loup.webp'));
        $product->addMedia(new ProductMedia()->setName('medias/ours.webp'));
        $product->addMedia(new ProductMedia()->setName('medias/renard.webp'));
        $product->addMedia(new ProductMedia()->setName('medias/lynx.jpg'));

        try {
            $this->assertSame([
                'https://example.org/medias/loup-highres.webp',
                'https://example.org/medias/ours.webp',
                'https://example.org/medias/renard.webp',
                'https://example.org/medias/lynx-highres.webp',
            ], $this->resolver($projectDir)->allForProduct($product));
        } finally {
            array_map(unlink(...), glob($projectDir . '/public/medias/*') ?: []);
            rmdir($projectDir . '/public/medias');
            rmdir($projectDir . '/public');
            rmdir($projectDir);
        }
    }

    private function resolver(string $projectDir = '/nonexistent'): ProductImageUrlResolver
    {
        return new ProductImageUrlResolver(
            new Packages(new PathPackage('/', new EmptyVersionStrategy())),
            new UrlHelper(new RequestStack([Request::create('https://example.org/shop')])),
            new ImageDimensionsReader(),
            $projectDir,
        );
    }
}
