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
use c975L\PaymentBundle\Service\GiftCardService;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ProductBasketItemProvider;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What may be dropped in a basket: the sheet of a draft or of a trashed product answers 404 or 410, but its items keep the ids an old page still carries
class ProductBasketItemProviderTest extends TestCase
{
    private function createProvider(?ProductItem $found = null): ProductBasketItemProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        // The checkout re-reads every item from the database rather than trusting what the basket stored, which is the whole point of asking again
        $itemService = $this->createStub(ProductItemServiceInterface::class);
        $itemService->method('findOneById')->willReturn($found);

        return new ProductBasketItemProvider(
            $itemService,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(GiftCardService::class),
            $translator,
        );
    }

    // One basket entry of the given quantity, keyed by item id as PaymentBundle stores it
    private function basketItems(int $quantity): array
    {
        return [1 => ['quantity' => $quantity]];
    }

    private function item(bool $hidden, bool $deleted = false): ProductItem
    {
        $product = new Product()->setHidden($hidden)->setIsDeleted($deleted);
        $item = new ProductItem()->setLimitedQuantity(null);
        $product->addItem($item);

        return $item;
    }

    // Prices are held VAT included, so a line carries the tax taken out of what it is sold for - never the rate multiplied by a quantity
    public function testALineCarriesTheTaxHeldInItsPrice(): void
    {
        $item = $this->item(false)->setPrice(1200)->setVat(20.0)->setCurrency('eur');
        $item->getProduct()->setTitle('Affiche')->setSlug('affiche');

        $data = $this->createProvider()->toBasketData($item, 3);

        $this->assertSame(3600, $data['total']);
        $this->assertSame(600, $data['totalVat']);
    }

    public function testAnItemOfAShownProductIsAdded(): void
    {
        $this->assertNull($this->createProvider()->validateAddition($this->item(false), 1));
    }

    public function testAnItemOfAHiddenProductIsRefused(): void
    {
        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($this->item(true), 1));
    }

    public function testAnItemOfATrashedProductIsRefused(): void
    {
        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($this->item(false, true), 1));
    }

    public function testAnItemTakenOfflineIsRefused(): void
    {
        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($this->item(false)->setHidden(true), 1));
    }

    public function testAnItemTakenOfflineWhileTheBasketHeldItIsRefusedAtCheckout(): void
    {
        $item = $this->item(false)->setHidden(true);

        $this->assertSame('label.unavailable', $this->createProvider($item)->validateCheckout(new Basket(), $this->basketItems(1)));
    }

    // A basket still holding one from before must be emptiable, whatever became of the product since
    public function testRemovingAnItemIsNeverRefused(): void
    {
        $this->assertNull($this->createProvider()->validateAddition($this->item(false, true), -1));
    }

    public function testABasketWhoseItemsAreStillAvailableChecksOut(): void
    {
        $item = $this->item(false);

        $this->assertNull($this->createProvider($item)->validateCheckout(new Basket(), $this->basketItems(2)));
    }

    // The gap validateAddition() cannot see: it is asked one click at a time, so five clicks on an item with one left pass it five times
    public function testABasketHoldingMoreThanIsLeftIsRefused(): void
    {
        $item = $this->item(false);
        $item->setLimitedQuantity(3)->setOrderedQuantity(2);

        $this->assertSame('label.no_more_items_available', $this->createProvider($item)->validateCheckout(new Basket(), $this->basketItems(2)));
        $this->assertNull($this->createProvider($item)->validateCheckout(new Basket(), $this->basketItems(1)));
    }

    // A basket sits for days, and what it holds can be taken offline in between
    public function testABasketHoldingAnItemOfAHiddenProductIsRefused(): void
    {
        $this->assertSame('label.unavailable', $this->createProvider($this->item(true))->validateCheckout(new Basket(), $this->basketItems(1)));
    }

    public function testABasketHoldingAnItemDeletedOutrightIsRefused(): void
    {
        $this->assertSame('label.unavailable', $this->createProvider(null)->validateCheckout(new Basket(), $this->basketItems(1)));
    }
}
