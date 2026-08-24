<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\PaymentBundle\Contract\GiftCardDesign;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Entity\GiftCard;
use c975L\PaymentBundle\Service\GiftCardService;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ProductBasketItemProvider;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// A card is money bought in advance: this bundle knows which of its items is one and what it is worth, PaymentBundle owns the money
class ProductGiftCardTest extends TestCase
{
    public function testAnOrdinaryItemIssuesNothing(): void
    {
        $giftCardService = $this->createMock(GiftCardService::class);
        $giftCardService->expects($this->never())->method('issue');

        $this->provider($this->item(), $giftCardService)->onBasketPaid(new Basket(), $this->basketItems(1, null), []);
    }

    // One card per unit bought, each with its own code: two cards of 20 € are not one of 40
    public function testEachUnitBoughtBecomesACardOfItsOwn(): void
    {
        $giftCardService = $this->createMock(GiftCardService::class);
        $giftCardService->expects($this->exactly(3))->method('issue')->with(5000, 'EUR');

        $this->provider($this->item(5000), $giftCardService)->onBasketPaid(new Basket(), $this->basketItems(3, 5000), []);
    }

    // The face value is the item's own and not its price: a shop is free to sell a 50 € card for 45
    public function testTheCardIsWorthItsFaceValueAndNotWhatItWasSoldFor(): void
    {
        $giftCardService = $this->createMock(GiftCardService::class);
        $giftCardService->expects($this->once())->method('issue')->with(5000, 'EUR');

        $item = $this->item(5000)->setPrice(4500);

        $this->provider($item, $giftCardService)->onBasketPaid(new Basket(), $this->basketItems(1, 5000), []);
    }

    // Read by the checkout to keep a promotional code off it (see Basket::CONTENT_FLAG_GIFT_CARD), on top of what the item is delivered as
    public function testAGiftCardEntryIsFlaggedAsOneWithoutLosingHowItIsDelivered(): void
    {
        $provider = $this->provider($this->item(5000), $this->createStub(GiftCardService::class));

        $flags = $provider->getContentFlags($this->basketItems(1, 5000)[12]);

        $this->assertSame(Basket::CONTENT_FLAG_GIFT_CARD, $flags & Basket::CONTENT_FLAG_GIFT_CARD);
        $this->assertSame(Basket::CONTENT_FLAG_PHYSICAL, $flags & Basket::CONTENT_FLAG_PHYSICAL);
    }

    public function testAnOrdinaryEntryCarriesNoGiftCardFlag(): void
    {
        $provider = $this->provider($this->item(), $this->createStub(GiftCardService::class));

        $this->assertSame(0, $provider->getContentFlags($this->basketItems(1, null)[12]) & Basket::CONTENT_FLAG_GIFT_CARD);
    }

    // Read off the basket and not off the catalogue: the visual a card was bought with is what the card keeps, whatever the shop has changed since
    public function testTheVisualTheCardWasBoughtWithIsHandedOverToTheCardItself(): void
    {
        $giftCardService = $this->capturingService($design);

        $items = $this->basketItems(1, 5000);
        $items[12]['parent'] += ['image' => 'medias/shop/noel.webp', 'giftCardText' => 'Bon cadeau', 'giftCardScratch' => false];

        $this->provider($this->item(5000), $giftCardService)->onBasketPaid(new Basket(), $items, []);

        $this->assertInstanceOf(GiftCardDesign::class, $design);
        $this->assertSame('medias/shop/noel.webp', $design->image);
        $this->assertSame('Bon cadeau', $design->text);
        $this->assertFalse($design->scratch);
    }

    // An order placed before the visual existed carries none, and the card keeps the panel a card is sold with by default
    public function testAnOrderCarryingNoVisualStillIssuesACard(): void
    {
        $giftCardService = $this->capturingService($design);

        $this->provider($this->item(5000), $giftCardService)->onBasketPaid(new Basket(), $this->basketItems(1, 5000), []);

        $this->assertInstanceOf(GiftCardDesign::class, $design);
        $this->assertNull($design->image);
        $this->assertNull($design->text);
        $this->assertTrue($design->scratch);
    }

    // What the shop copies onto the basket, so the card can be printed long after the catalogue has moved on
    public function testTheBasketCopiesTheVisualOfTheProductItWasFilledFrom(): void
    {
        $product = new Product()
            ->setTitle('Carte cadeau')
            ->setSlug('carte-cadeau')
            ->setGiftCardText('Bon cadeau')
            ->setGiftCardScratch(false)
        ;
        $item = $this->item(5000)->setProduct($product);

        $data = $this->provider($item, $this->createStub(GiftCardService::class))->toBasketData($item, 1);

        $this->assertSame('Bon cadeau', $data['parent']['giftCardText']);
        $this->assertFalse($data['parent']['giftCardScratch']);
    }

    // Answered off the items rather than stored twice: an amount is what makes a product a card
    public function testAProductIsACardWhenOneOfItsItemsCarriesAnAmount(): void
    {
        $product = new Product()->setTitle('Carte cadeau')->setSlug('carte-cadeau');
        $this->assertFalse($product->isGiftCard());

        $product->addItem(new ProductItem()->setTitle('Article')->setSlug('article'));
        $this->assertFalse($product->isGiftCard());

        $product->addItem(new ProductItem()->setTitle('50 €')->setSlug('50')->setGiftCardValue(5000));
        $this->assertTrue($product->isGiftCard());
    }

    // The visual the shop hands over, kept as it was handed: what issue() is called with is the whole of what the card is printed from
    private function capturingService(?GiftCardDesign &$design): GiftCardService
    {
        $design = null;

        $giftCardService = $this->createStub(GiftCardService::class);
        $giftCardService->method('issue')->willReturnCallback(
            static function (int $amount, string $currency, ?Basket $basket, ?\DateTimeInterface $validUntil, ?GiftCardDesign $handedOver) use (&$design): GiftCard {
                $design = $handedOver;

                return new GiftCard();
            }
        );

        return $giftCardService;
    }

    private function item(?int $giftCardValue = null): ProductItem
    {
        return new ProductItem()
            ->setTitle('Carte cadeau')
            ->setSlug('carte-cadeau')
            ->setPrice(5000)
            ->setCurrency('EUR')
            ->setGiftCardValue($giftCardValue)
            ->setProduct(new Product()->setTitle('Carte cadeau')->setSlug('carte-cadeau'))
        ;
    }

    // One basket entry as PaymentBundle stores it, keyed by item id
    private function basketItems(int $quantity, ?int $giftCardValue): array
    {
        return [
            12 => [
                'item' => ['id' => 12, 'file' => null, 'service' => false, 'giftCardValue' => $giftCardValue, 'price' => 5000, 'currency' => 'EUR'],
                'parent' => ['title' => 'Carte cadeau'],
                'type' => 'product',
                'quantity' => $quantity,
                'totalVat' => 0,
                'total' => $quantity * 5000,
            ],
        ];
    }

    private function provider(ProductItem $item, GiftCardService $giftCardService): ProductBasketItemProvider
    {
        $itemService = $this->createStub(ProductItemServiceInterface::class);
        $itemService->method('findOneById')->willReturn($item);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProductBasketItemProvider($itemService, $this->createStub(MessageBusInterface::class), $giftCardService, $translator);
    }
}
