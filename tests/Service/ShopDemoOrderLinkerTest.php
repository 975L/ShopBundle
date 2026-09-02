<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Entity\Payment;
use c975L\PaymentBundle\Service\GiftCardService;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductBasketItemProvider;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use c975L\ShopBundle\Service\ShopDemoOrderLinker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The orders a demo shop has already taken, written once its catalogue has been flushed
class ShopDemoOrderLinkerTest extends TestCase
{
    private int $nextId = 1;

    private function product(string $slug, int $price, bool $service = false): Product
    {
        $item = new ProductItem()
            ->setTitle('Standard')
            ->setSlug($slug . '-standard')
            ->setPrice($price)
            ->setVat('20')
            ->setService($service)
        ;
        new \ReflectionProperty(ProductItem::class, 'id')->setValue($item, ++$this->nextId);

        $product = new Product()->setTitle($slug)->setSlug($slug);
        new \ReflectionProperty(Product::class, 'id')->setValue($product, ++$this->nextId);
        $product->addItem($item);

        return $product;
    }

    /** @param array<string, Product> $catalogue */
    private function linker(array $catalogue, string $currency = 'EUR'): ShopDemoOrderLinker
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => $catalogue[$criteria['slug']] ?? null,
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($currency);

        return new ShopDemoOrderLinker($repository, $this->basketItemProvider(), $configService);
    }

    // The real one rather than a stub: what a line is shaped like is the whole point of writing an order beside the catalogue, and a stub would let the shape drift
    private function basketItemProvider(): ProductBasketItemProvider
    {
        return new ProductBasketItemProvider(
            $this->createStub(ProductItemServiceInterface::class),
            $this->createStub(MessageBusInterface::class),
            $this->createStub(GiftCardService::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(UrlGeneratorInterface::class),
        );
    }

    /** @return list<object> */
    private function fixtures(array $catalogue, string $currency = 'EUR'): array
    {
        return iterator_to_array($this->linker($catalogue, $currency)->getLinkedDemoFixtures(), false);
    }

    private function catalogue(): array
    {
        return [
            'table-basse-chene' => $this->product('table-basse-chene', 24900),
            'coussin-lin' => $this->product('coussin-lin', 3900),
            'atelier-decoration' => $this->product('atelier-decoration', 6000, true),
        ];
    }

    // Two orders, and the payment of each written before it: the order is the owning side, and a flush needs both
    public function testItWritesTwoPaidOrdersAndTheirPayments(): void
    {
        $fixtures = $this->fixtures($this->catalogue());

        $this->assertCount(4, $fixtures);
        $this->assertInstanceOf(Payment::class, $fixtures[0]);
        $this->assertInstanceOf(Basket::class, $fixtures[1]);
        $this->assertInstanceOf(Payment::class, $fixtures[2]);
        $this->assertInstanceOf(Basket::class, $fixtures[3]);
    }

    // What the shipping screen filters on, and what its "mark as sent" action asks of a row before it offers itself
    public function testThePostedOrderIsPaidNumberedAndStillWaitingToGoOut(): void
    {
        $order = $this->fixtures($this->catalogue())[1];

        $this->assertSame('paid', $order->getStatus());
        $this->assertSame('2026-0114', $order->getNumber());
        $this->assertNull($order->getItemsShipped());
        $this->assertSame(Basket::CONTENT_FLAG_PHYSICAL, $order->getContentFlags() & Basket::CONTENT_FLAG_PHYSICAL);
    }

    // A total that does not answer for its lines is exactly what the integrity check is there to catch, the shipping being added on top of it by getPayable()
    public function testTheTotalAnswersForItsLinesAndThePayableAddsTheShipping(): void
    {
        $order = $this->fixtures($this->catalogue())[1];

        // One table at 249,00 and two cushions at 39,00, posted for 12,00
        $this->assertSame(24900 + 2 * 3900, $order->getTotal());
        $this->assertSame(24900 + 2 * 3900 + 1200, $order->getPayable());
        $this->assertSame(1200, $order->getShipping());
        $this->assertSame(3, $order->getQuantity());
    }

    // The order and its payment must say the same thing, or the shop is charged for one amount and delivers another
    public function testThePaymentAnswersForTheOrderItSettles(): void
    {
        [$payment, $order] = \array_slice($this->fixtures($this->catalogue()), 0, 2);

        $this->assertTrue($payment->isFinished());
        $this->assertSame($order->getPayable(), $payment->getAmount());
        $this->assertSame($order->getCurrency(), $payment->getCurrency());
        $this->assertSame($payment, $order->getPayment());
    }

    // The second order is a service and not a parcel: nothing to post, and a shipping screen that would otherwise show every order it has
    public function testTheSecondOrderIsAServiceWithNothingToPost(): void
    {
        $order = $this->fixtures($this->catalogue())[3];

        $this->assertSame('2026-0115', $order->getNumber());
        $this->assertSame(Basket::CONTENT_FLAG_SERVICE, $order->getContentFlags() & Basket::CONTENT_FLAG_SERVICE);
        $this->assertSame(0, $order->getContentFlags() & Basket::CONTENT_FLAG_PHYSICAL);
        $this->assertSame(0, $order->getShipping());
    }

    // A shop that seeded no catalogue - no placeholder picture, no sample file - has no order to write either
    public function testItWritesNothingWithoutACatalogue(): void
    {
        $this->assertSame([], $this->fixtures([]));
    }

    // What the shop sells in, the setting being empty until someone fills it on camera
    public function testItFallsBackToEurosWhenTheShopHasNotSaidWhatItSellsIn(): void
    {
        $order = $this->fixtures($this->catalogue(), '')[1];

        $this->assertSame('EUR', $order->getCurrency());
    }
}
