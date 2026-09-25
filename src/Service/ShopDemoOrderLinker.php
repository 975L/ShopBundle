<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PaymentBundle\Contract\BasketLine;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Entity\Payment;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;

// The orders a demo shop has already taken, written once its catalogue is flushed: one posted and left unshipped for the guided project, one for a service, one charged below its total for the basket integrity check to catch
class ShopDemoOrderLinker implements DemoFixtureLinkerInterface
{
    // Written down rather than taken from the clock: a demo is reloaded often, and "ordered yesterday" would say something else in every take of the same recorded sequence
    private const string ORDERED_PHYSICAL = '2026-02-24 10:34:00';
    private const string ORDERED_SERVICE = '2026-02-27 21:06:00';

    // Relative, unlike the two above: the basket integrity check reads the last twelve months only, and a date written down would have this order drop out of it a year on. The first day of last month holds still for a whole month of takes
    private const string ORDERED_MISMATCH = 'first day of last month 14:52';

    // In cents, what a promotional code took off the charge without ever being written onto the order
    private const int MISMATCH_DISCOUNT = 500;

    // What a gateway hands back, made up here - no payment provider has been called, and none may be from a demo
    private const string GATEWAY = 'stripe';

    // What an order is written in when the shop has not been told yet, which is where a demo starts: the guided project fills the setting in on camera, and an order placed before it still has to say what it was paid in
    private const string FALLBACK_CURRENCY = 'EUR';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductBasketItemProvider $basketItemProvider,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getLinkedDemoFixtures(): iterable
    {
        $physical = $this->order(
            '2026-0114',
            self::ORDERED_PHYSICAL,
            ['table-basse-chene' => 1, 'coussin-lin' => 2],
            'claire.moreau@example.com',
            'Claire Moreau',
            '14 rue des Tanneurs',
            'Annecy',
            '74000',
            1200,
        );

        // Nothing to post, so nothing to wait for: the order is closed the moment it is paid
        $service = $this->order(
            '2026-0115',
            self::ORDERED_SERVICE,
            ['atelier-decoration' => 1],
            'paul.riviere@example.com',
            'Paul Rivière',
            '3 place du Marché',
            'Rumilly',
            '74150',
            0,
        );

        // Charged below what it adds up to, which the basket integrity check reports (a promotional code left off the order). A service, so the shipping screen still holds the one parcel its guided project marks as sent
        $mismatch = $this->order(
            '2026-0116',
            self::ORDERED_MISMATCH,
            ['atelier-decoration' => 1],
            'lea.martin@example.com',
            'Léa Martin',
            '8 avenue de Genève',
            'Annecy',
            '74000',
            0,
        );
        $mismatch?->getPayment()?->setAmount($mismatch->getPayable() - self::MISMATCH_DISCOUNT);

        foreach ([$physical, $service, $mismatch] as $order) {
            if (null === $order) {
                continue;
            }

            yield $order->getPayment();
            yield $order;
        }
    }

    /**
     * One paid order, its lines copied off the catalogue the way a checkout copies them.
     *
     * @param array<string, int> $quantities product slug => how many of its first item
     */
    private function order(
        string $number,
        string $orderedAt,
        array $quantities,
        string $email,
        string $name,
        string $address,
        string $city,
        string $zip,
        int $shipping,
    ): ?Basket {
        $items = [];
        $total = 0;
        $quantity = 0;
        $flags = 0;

        foreach ($quantities as $slug => $howMany) {
            $product = $this->productRepository->findOneBy(['slug' => $slug]);
            $item = $product?->getItems()->first() ?: null;
            if (null === $item) {
                continue;
            }

            $line = BasketLine::stamp($this->basketItemProvider->toBasketData($item, $howMany));
            $items[$this->basketItemProvider->getKind()][$item->getId()] = $line;
            $total += $line['total'];
            $quantity += $howMany;
            $flags |= $this->basketItemProvider->getContentFlags($line);
        }

        // A catalogue that seeded nothing - no placeholder picture, no sample file - leaves no order to write either
        if ([] === $items) {
            return null;
        }

        $ordered = new \DateTime($orderedAt);
        $payment = new Payment()
            ->setFinished(true)
            ->setAmount($total + $shipping)
            ->setCurrency($this->currency())
            ->setGateway(self::GATEWAY)
            ->setTransactionId('pi_demo_' . $number)
            ->setPaymentMethod('card')
            ->setCreation($ordered)
            ->setModification($ordered)
        ;

        return new Basket()
            ->setNumber($number)
            ->setItems($items)
            ->setStatus('paid')
            ->setEmail($email)
            ->setName($name)
            ->setAddress($address)
            ->setCity($city)
            ->setZip($zip)
            ->setCountry('FR')
            // The lines alone, the shipping being added on top of it by Basket::getPayable() - a total already holding it is what the basket integrity check reads as a mismatch
            ->setTotal($total)
            ->setShipping($shipping)
            ->setQuantity($quantity)
            ->setCurrency($this->currency())
            ->setContentFlags($flags)
            ->setCreation($ordered)
            ->setModification($ordered)
            ->setPayment($payment)
        ;
    }

    // What the shop sells in, the setting being empty until someone fills it - an order says what it was paid in whatever the shop has been told since
    private function currency(): string
    {
        $currency = $this->configService->get('shop-currency');

        return \is_string($currency) && '' !== $currency ? $currency : self::FALLBACK_CURRENCY;
    }
}
