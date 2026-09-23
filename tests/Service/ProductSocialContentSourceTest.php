<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductSocialContentSource;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use PHPUnit\Framework\TestCase;

class ProductSocialContentSourceTest extends TestCase
{
    /** @var list<int>|null */
    private ?array $excludedIds = null;

    private function createProduct(bool $hidden = false): Product
    {
        $product = new Product()->setTitle('Mug')->setSlug('mug')->setDescription('<p>Un mug &amp; son dessin</p>')->setHidden($hidden);
        $product->addMedia(new ProductMedia()->setName('medias/shop/products/mug-abc.webp'));
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 5);

        return $product;
    }

    private function createSource(?Product $product, ?string $siteUrl = 'https://example.org'): ProductSocialContentSource
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findAvailableProductsExcluding')->willReturnCallback(function (array $excludedIds) use ($product): array {
            $this->excludedIds = $excludedIds;

            return null === $product ? [] : [$product];
        });
        $repository->method('find')->willReturn($product);

        $urlResolver = $this->createStub(ShopPublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (string $route, array $parameters): ?string => null === $siteUrl ? null : $siteUrl . '/shop/products/' . $parameters['slug']);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new ProductSocialContentSource($repository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    public function testTheNextProductIsHandedOverWithItsFirstPhotograph(): void
    {
        $content = $this->createSource($this->createProduct())->getNextContent(['3']);

        $this->assertSame([3], $this->excludedIds);
        $this->assertSame('5', $content?->sourceId);
        $this->assertSame('https://example.org/shop/products/mug', $content->url);
        $this->assertSame('/var/www/site/public/medias/shop/products/mug-abc.webp', $content->imagePath);
        $this->assertSame('https://example.org/medias/shop/products/mug-abc.webp', $content->imageUrl);
        $this->assertSame(['description' => 'Un mug & son dessin'], $content->variables);
    }

    public function testNothingIsHandedOverWhileTheSiteUrlIsUnset(): void
    {
        $this->assertNull($this->createSource($this->createProduct(), null)->getNextContent([]));
    }

    public function testAProductIsOfferedAgainAfterASeason(): void
    {
        $this->assertSame(90, $this->createSource(null)->getRepeatAfterDays());
    }

    // Taken off the shop after its post was prepared: publishing the draft a day later must not put it back
    public function testAProductHiddenSinceIsNotReadAgain(): void
    {
        $this->assertSame('5', $this->createSource($this->createProduct())->getContent('5')?->sourceId);
        $this->assertNull($this->createSource($this->createProduct(hidden: true))->getContent('5'));
    }

    public function testAProductNotAvailableYetIsNotReadAgain(): void
    {
        $this->assertNull($this->createSource($this->createProduct()->setAvailableAt(new \DateTime('+1 week')))->getContent('5'));
    }
}
