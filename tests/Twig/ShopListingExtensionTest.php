<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopServiceInterface;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\ShopBundle\Twig\Extension\ShopListingExtension;
use c975L\UiBundle\Model\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ShopListingExtensionTest extends TestCase
{
    private function createExtension(array $query = [], ?ShopServiceInterface $shopService = null, ?ProductServiceInterface $productService = null): ShopListingExtension
    {
        return new ShopListingExtension(
            $shopService ?? $this->createStub(ShopServiceInterface::class),
            $productService ?? $this->createStub(ProductServiceInterface::class),
            $this->createStub(ShopTranslator::class),
            new RequestStack([new Request($query)]),
        );
    }

    // The same parameters in another order are the same listing, and so the same entry
    public function testTheKeyIgnoresTheOrderOfTheQuery(): void
    {
        $this->assertSame(
            $this->createExtension(['order' => 'newest', 'p' => '2'])->getListingKey(),
            $this->createExtension(['p' => '2', 'order' => 'newest'])->getListingKey(),
        );
        $this->assertNotSame(
            $this->createExtension(['p' => '2'])->getListingKey(),
            $this->createExtension(['p' => '3'])->getListingKey(),
        );
    }

    // A query the listing reads keeps its entry for ever, the catalog tags emptying it
    public function testAQueryTheListingReadsHasNoTtl(): void
    {
        $this->assertNull($this->createExtension()->getListingTtl());
        $this->assertNull($this->createExtension(['order' => 'price_asc', 'price' => '0-1000', 'format' => 'digital', 'stock' => 'available', 'p' => '2'])->getListingTtl());
    }

    // A stranger's parameter makes an entry of its own, which lives an hour only
    public function testAForeignParameterGetsAnHour(): void
    {
        $this->assertSame(3600, $this->createExtension(['utm_source' => 'newsletter'])->getListingTtl());
        $this->assertSame(3600, $this->createExtension(['order' => 'newest', 'fbclid' => 'abc'])->getListingTtl());
    }

    // The listing and its structured data are two fragments of one page, read with one query
    public function testTheListingIsReadOncePerRequest(): void
    {
        $shopService = $this->createMock(ShopServiceInterface::class);
        $shopService->expects($this->once())
            ->method('findAllProductsPaginated')
            ->willReturn(new Pagination([], 1, 12, 0));
        $extension = $this->createExtension(shopService: $shopService);

        $this->assertSame($extension->getListing(), $extension->getListing());
    }

    // Read once per category and per request, a reset starting over
    public function testTheCategoryProductsAreReadOnceUntilReset(): void
    {
        $product = new Product();
        $productService = $this->createMock(ProductServiceInterface::class);
        $productService->expects($this->exactly(2))
            ->method('findByCategorySlug')
            ->with('books')
            ->willReturn([$product]);
        $extension = $this->createExtension(productService: $productService);
        $category = new ProductCategory()->setSlug('books');

        $this->assertSame([$product], $extension->getCategoryProducts($category));
        $this->assertSame([$product], $extension->getCategoryProducts($category));

        $extension->reset();
        $this->assertSame([$product], $extension->getCategoryProducts($category));
    }
}
