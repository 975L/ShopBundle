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
use c975L\ShopBundle\Repository\ProductAffinityRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductRecommendationService;
use PHPUnit\Framework\TestCase;

// What a product sheet recommends: the products an editor picked, and the calculated affinities only when nobody picked any
class ProductRecommendationServiceTest extends TestCase
{
    public function testThePickedProductsComeBeforeTheCalculatedOnes(): void
    {
        $picked = $this->product('picked');
        $product = $this->product('main')->addRelatedProduct($picked);

        // The repository is told to answer with something else entirely: a picked product reaching the sheet proves the calculation was never asked
        $recommendations = $this->service([$this->product('calculated')])->getSimilarProducts($product);

        $this->assertSame([$picked], $recommendations);
    }

    // A draft has never been online and a trashed product answers 410: neither belongs in a block naming it, picked by hand or not
    public function testAPickedProductTheShopIsNotStandingBehindIsLeftOut(): void
    {
        $draft = $this->product('draft')->setIsPublished(false);
        $trashed = $this->product('trashed')->setIsDeleted(true);
        $product = $this->product('main')->addRelatedProduct($draft)->addRelatedProduct($trashed);

        $this->assertSame([], $this->service()->getSimilarProducts($product));
    }

    public function testAProductWithoutAPickFallsBackOnTheCalculatedAffinities(): void
    {
        $calculated = $this->product('calculated');

        $recommendations = $this->service([$calculated])->getSimilarProducts($this->product('main'));

        $this->assertSame([$calculated], $recommendations);
    }

    // A product going with itself would fill the recommendations of its own sheet with the sheet being read
    public function testAProductCannotBeItsOwnRelatedProduct(): void
    {
        $product = $this->product('main');
        $product->addRelatedProduct($product);

        $this->assertCount(0, $product->getRelatedProducts());
    }

    public function testThePicksAreCappedAtTheLimitAsked(): void
    {
        $product = $this->product('main');
        foreach (range(1, 6) as $i) {
            $product->addRelatedProduct($this->product('pick-' . $i));
        }

        $this->assertCount(2, $this->service()->getSimilarProducts($product, 2));
    }

    // A service whose repository answers the calculated path with the given products, all of them scoring on the price they share
    private function service(array $calculated = []): ProductRecommendationService
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findRandomProducts')->willReturn($calculated);
        $productRepository->method('findByCategoriesExcluding')->willReturn($calculated);

        return new ProductRecommendationService(
            $productRepository,
            $this->createStub(ProductAffinityRepository::class),
            $this->createStub(ProductItemRepository::class),
        );
    }

    private function product(string $slug): Product
    {
        return new Product()
            ->setTitle($slug)
            ->setSlug($slug)
            ->setIsPublished(true)
        ;
    }
}
