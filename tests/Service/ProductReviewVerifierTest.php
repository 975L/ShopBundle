<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductReviewVerifier;
use PHPUnit\Framework\TestCase;

// Whether the person leaving a review on a product actually bought it - what the "vérifié" badge stands for
class ProductReviewVerifierTest extends TestCase
{
    public function testItAnswersForProductsAndForNothingElse(): void
    {
        $verifier = $this->verifier();

        $this->assertTrue($verifier->supports('shop_product'));
        $this->assertFalse($verifier->supports('book'));
    }

    // The lines of an order are keyed by item id, so what is compared is ids and never a title or a slug
    public function testAnOrderHoldingOneOfTheProductsItemsVerifiesTheReview(): void
    {
        $verifier = $this->verifier(
            product: $this->product([239, 240]),
            baskets: [$this->basket([239 => ['quantity' => 1]])]
        );

        $this->assertTrue($verifier->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    public function testAnOrderHoldingNoneOfThemLeavesItUnverified(): void
    {
        $verifier = $this->verifier(
            product: $this->product([239]),
            baskets: [$this->basket([777 => ['quantity' => 1]])]
        );

        $this->assertFalse($verifier->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    // An address that bought nothing here, which is every address until it does
    public function testAnAddressWithNoOrderIsNotVerified(): void
    {
        $this->assertFalse($this->verifier(product: $this->product([239]))->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    // A review on a product the catalogue no longer holds cannot be checked against anything
    public function testAProductThatIsGoneIsNotVerified(): void
    {
        $this->assertFalse($this->verifier()->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    // Nothing to compare an order against: a product sold through no item at all was never bought
    public function testAProductWithNoItemIsNotVerified(): void
    {
        $verifier = $this->verifier(
            product: $this->product([]),
            baskets: [$this->basket([239 => ['quantity' => 1]])]
        );

        $this->assertFalse($verifier->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    // An order taken before this bundle filed its lines under "product" carries none of them, and is walked past rather than crashed on
    public function testAnOrderCarryingNoProductLineIsWalkedPast(): void
    {
        $basket = new Basket();
        $basket->setItems(['crowdfunding' => [1 => ['quantity' => 1]]]);

        $verifier = $this->verifier(product: $this->product([239]), baskets: [$basket]);

        $this->assertFalse($verifier->hasObtained('shop_product', 12, 'marie@example.org'));
    }

    /**
     * @param int[] $itemIds
     */
    private function product(array $itemIds): Product
    {
        $product = new Product();

        foreach ($itemIds as $id) {
            $item = new ProductItem();
            new \ReflectionProperty(ProductItem::class, 'id')->setValue($item, $id);
            $product->addItem($item);
        }

        return $product;
    }

    /**
     * @param array<int, array<string, mixed>> $productLines
     */
    private function basket(array $productLines): Basket
    {
        return new Basket()->setItems(['product' => $productLines]);
    }

    /**
     * @param Basket[] $baskets
     */
    private function verifier(?Product $product = null, array $baskets = []): ProductReviewVerifier
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('find')->willReturn($product);

        $basketRepository = $this->createStub(BasketRepository::class);
        $basketRepository->method('findPaidByEmail')->willReturn($baskets);

        return new ProductReviewVerifier($productRepository, $basketRepository);
    }
}
