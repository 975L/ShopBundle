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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Service\ProductStateService;
use PHPUnit\Framework\TestCase;

// What a product card says of itself - badge, price and formats - all read from the items rather than stored on the product
class ProductStateServiceTest extends TestCase
{
    private ProductStateService $service;

    protected function setUp(): void
    {
        $this->service = new ProductStateService();
    }

    public function testAProductWithoutItemHasNoPriceAndNoFormat(): void
    {
        $state = $this->service->getState(new Product());

        $this->assertNull($state['price']);
        $this->assertSame([], $state['formats']);
        $this->assertFalse($state['soldOut']);
        $this->assertNull($state['badge']);
    }

    public function testThePriceIsTheLowestOfTheItems(): void
    {
        $product = $this->product([$this->item(2500), $this->item(900), $this->item(1900)]);

        $this->assertSame(900, $this->service->getLowestPrice($product));
        $this->assertFalse($this->service->getState($product)['singlePrice']);
    }

    public function testAProductSoldAtOneSinglePriceSaysSo(): void
    {
        $product = $this->product([$this->item(1900), $this->item(1900)]);

        $this->assertTrue($this->service->getState($product)['singlePrice']);
    }

    public function testAProductWhoseItemsAreAllOrderedIsOutOfStock(): void
    {
        $product = $this->product([$this->item(1900, 3, 3), $this->item(2500, 0)]);
        $state = $this->service->getState($product);

        $this->assertTrue($state['soldOut']);
        $this->assertSame('label.out_of_stock', $state['badge']);
    }

    // Withdrawn is not out of stock: nothing is promised on a product whose every item was capped at 0
    public function testAProductWhoseItemsWereAllWithdrawnIsSoldOut(): void
    {
        $product = $this->product([$this->item(1900, 0), $this->item(2500, 0)]);
        $state = $this->service->getState($product);

        $this->assertTrue($state['soldOut']);
        $this->assertSame('label.sold_out', $state['badge']);
    }

    public function testAnItemWithNoLimitIsAlwaysBuyable(): void
    {
        $product = $this->product([$this->item(1900, null)]);

        $this->assertFalse($this->service->getState($product)['soldOut']);
    }

    public function testAProductStillToComeSaysSoBeforeAnythingElse(): void
    {
        $product = $this->product([$this->item(1900, 0)]);
        $product->setAvailableAt(new \DateTime('+10 days'));
        $state = $this->service->getState($product);

        $this->assertSame('label.coming_soon', $state['badge']);
        $this->assertNotNull($state['availableAt']);
    }

    public function testACappedStockComesBeforeAPriceAndABirthday(): void
    {
        $product = $this->product([$this->item(0, 5)]);
        $product->setCreation(new \DateTime());

        $this->assertSame('label.limited_quantity', $this->service->getState($product)['badge']);
    }

    public function testAFreeProductComesBeforeARecentOne(): void
    {
        $product = $this->product([$this->item(0)]);
        $product->setCreation(new \DateTime());

        $this->assertSame('label.free', $this->service->getState($product)['badge']);
    }

    public function testAProductCreatedWithinThirtyDaysIsNew(): void
    {
        $product = $this->product([$this->item(1900)]);
        $product->setCreation(new \DateTime('-3 days'));

        $this->assertSame('label.new', $this->service->getState($product)['badge']);
    }

    public function testAnOlderProductWearsNoBadgeAtAll(): void
    {
        $product = $this->product([$this->item(1900)]);
        $product->setCreation(new \DateTime('-90 days'));

        $this->assertNull($this->service->getState($product)['badge']);
    }

    public function testTheFormatsAreTheDistinctKindsOfItemsInReadingOrder(): void
    {
        $digital = $this->item(900);
        $digital->setFile(new ProductItemFile()->setName('livre.pdf'));

        $service = $this->item(4900);
        $service->setService(true);

        $product = $this->product([$digital, $service, $this->item(1900), $this->item(2900)]);

        $this->assertSame(['label.physical', 'label.digital', 'label.service'], $this->service->getState($product)['formats']);
    }

    public function testAnItemNamesItsOwnKind(): void
    {
        $this->assertSame('label.physical', $this->service->getItemFormat($this->item(1900)));

        $digital = $this->item(900);
        $digital->setFile(new ProductItemFile()->setName('livre.epub'));
        $this->assertSame('label.digital', $this->service->getItemFormat($digital));

        $service = $this->item(4900);
        $service->setService(true);
        $this->assertSame('label.service', $this->service->getItemFormat($service));
    }

    public function testAnItemNamesTheFormatOfItsFile(): void
    {
        $this->assertNull($this->service->getItemFileFormat($this->item(1900)));

        $digital = $this->item(900);
        $digital->setFile(new ProductItemFile()->setName('livre.pdf'));
        $this->assertSame('PDF', $this->service->getItemFileFormat($digital));

        $extensionless = $this->item(900);
        $extensionless->setFile(new ProductItemFile()->setName('livre'));
        $this->assertNull($this->service->getItemFileFormat($extensionless));
    }

    public function testAPreviousPriceBelowThePriceIsNotAnOffer(): void
    {
        // Equal, below, and absent: none of the three is a price cut, and none of them may reach a card or a graph
        $this->assertNull($this->service->getItemPriceBefore($this->item(1900)->setPriceBefore(1900)));
        $this->assertNull($this->service->getItemPriceBefore($this->item(1900)->setPriceBefore(900)));
        $this->assertNull($this->service->getItemPriceBefore($this->item(1900)));
        $this->assertNull($this->service->getItemDiscount($this->item(1900)->setPriceBefore(1900)));
    }

    public function testADiscountIsCountedOnThePreviousPrice(): void
    {
        $item = $this->item(1500)->setPriceBefore(2000);

        $this->assertSame(2000, $this->service->getItemPriceBefore($item));
        $this->assertSame(25, $this->service->getItemDiscount($item));
    }

    // A cut too small to round up to a whole percent says nothing rather than "-0 %"
    public function testACutRoundingDownToNothingPublishesNoDiscount(): void
    {
        $this->assertNull($this->service->getItemDiscount($this->item(9970)->setPriceBefore(10000)));
    }

    public function testTheCardsPreviousPriceIsTheOneOfTheItemItPricesItselfOn(): void
    {
        // The dearest item is the one on offer, the cheapest is not: the card prices itself on the cheapest, so it must show no previous price at all
        $product = $this->product([$this->item(3000)->setPriceBefore(5000), $this->item(900)]);
        $state = $this->service->getState($product);

        $this->assertSame(900, $state['price']);
        $this->assertNull($state['priceBefore']);
        $this->assertNull($state['discount']);
    }

    public function testAProductOnOfferWearsItsDiscountBadge(): void
    {
        $state = $this->service->getState($this->product([$this->item(1500)->setPriceBefore(2000)]));

        $this->assertSame('label.discount', $state['badge']);
        $this->assertSame(25, $state['discount']);
        $this->assertSame(2000, $state['priceBefore']);
    }

    // What cannot be bought still comes first: an offer on a product nobody can order is not something to act on
    public function testASoldOutProductKeepsItsBadgeOverADiscount(): void
    {
        $product = $this->product([$this->item(1500, 2, 2)->setPriceBefore(2000)]);

        $this->assertSame('label.out_of_stock', $this->service->getState($product)['badge']);
    }

    // "-100 %" over something given away is the misleading way of saying "free"
    public function testSomethingGivenAwaySaysFreeRatherThanADiscount(): void
    {
        $product = $this->product([$this->item(0)->setPriceBefore(2000)]);

        $this->assertSame('label.free', $this->service->getState($product)['badge']);
    }

    // A product carrying the given items
    public function testAnItemTakenOfflineSetsNeitherThePriceNorTheFormats(): void
    {
        $offline = $this->item(500)->setHidden(true);
        $product = $this->product([$this->item(1900), $offline]);
        $state = $this->service->getState($product);

        $this->assertSame(1900, $state['price']);
        $this->assertSame(1900, $this->service->getLowestPrice($product));
        $this->assertTrue($state['singlePrice']);
    }

    public function testAProductWhoseItemsAreAllOfflineReadsLikeOneWithNoItemAtAll(): void
    {
        $product = $this->product([$this->item(1900)->setHidden(true)]);
        $state = $this->service->getState($product);

        $this->assertNull($state['price']);
        $this->assertSame([], $state['formats']);
        $this->assertFalse($state['soldOut']);
    }

    // The rule three readers share - the card's badge, the add button and the stock alerts - which is why it is public rather than written out a third time
    public function testAnItemIsBuyableUntilItsCapIsReached(): void
    {
        $service = new ProductStateService();

        $this->assertTrue($service->isItemAvailable($this->item(1000, 5, 4)));
        $this->assertFalse($service->isItemAvailable($this->item(1000, 5, 5)));
        $this->assertTrue($service->isItemAvailable($this->item(1000, null, 100)));
        $this->assertFalse($service->isItemAvailable($this->item(1000, 0)));
    }

    // Sold out is not withdrawn: an item capped at 0 was taken off sale and is not expected back, so nothing is offered on it
    public function testAnItemWithdrawnFromSaleIsNotSoldOut(): void
    {
        $service = new ProductStateService();

        $this->assertTrue($service->isItemSoldOut($this->item(1000, 5, 5)));
        $this->assertFalse($service->isItemSoldOut($this->item(1000, 0)));
        $this->assertFalse($service->isItemSoldOut($this->item(1000, 5, 4)));
        $this->assertFalse($service->isItemSoldOut($this->item(1000, null, 100)));
    }

    private function product(array $items): Product
    {
        $product = new Product();

        foreach ($items as $item) {
            $product->addItem($item);
        }

        return $product;
    }

    // An item at that price, unlimited unless a cap is given
    private function item(int $price, ?int $limited = null, int $ordered = 0): ProductItem
    {
        return new ProductItem()
            ->setPrice($price)
            ->setLimitedQuantity($limited)
            ->setOrderedQuantity($ordered)
        ;
    }
}
