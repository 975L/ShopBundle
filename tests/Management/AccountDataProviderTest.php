<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Management\AccountDataProvider;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use PHPUnit\Framework\TestCase;

class AccountDataProviderTest extends TestCase
{
    // The alerts of the member's address, named by product and item
    public function testExportsTheStockAlertsOfTheAddress(): void
    {
        $item = new ProductItem()->setTitle('Taille M')->setProduct(new Product()->setTitle('T-shirt'));
        $alert = new ProductItemStockAlert()->setProductItem($item)->setEmail('user@example.test');

        $data = $this->provider(alerts: [$alert])->getAccountData($this->user());

        $this->assertSame('T-shirt', $data['stock_alerts'][0]['product']);
        $this->assertSame('Taille M', $data['stock_alerts'][0]['item']);
    }

    // The download links of the member's orders, tied to them by number and without their secret token
    public function testExportsTheDownloadsOfTheOrders(): void
    {
        $basket = $this->createStub(Basket::class);
        $basket->method('getId')->willReturn(7);
        $basket->method('getNumber')->willReturn('2026-000007');
        $download = new ProductItemDownload()->setBasketId(7)->setFilename('book.pdf')->setToken('secret')->setExpiresAt(new \DateTimeImmutable());

        $data = $this->provider(baskets: [$basket], downloads: [$download])->getAccountData($this->user());

        $this->assertSame('2026-000007', $data['downloads'][0]['order']);
        $this->assertSame('book.pdf', $data['downloads'][0]['file']);
        $this->assertNotContains('secret', $data['downloads'][0]);
    }

    // Nothing in the shop, no key at all
    public function testNothingWithoutShopData(): void
    {
        $this->assertSame([], $this->provider()->getAccountData($this->user()));
    }

    // A member whose address is user@example.test
    private function user(): InactivityAwareInterface
    {
        $user = $this->createStub(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn('user@example.test');

        return $user;
    }

    /**
     * @param list<ProductItemStockAlert> $alerts
     * @param list<Basket>                $baskets
     * @param list<ProductItemDownload>   $downloads
     */
    private function provider(array $alerts = [], array $baskets = [], array $downloads = []): AccountDataProvider
    {
        $stockAlertRepository = $this->createStub(ProductItemStockAlertRepository::class);
        $stockAlertRepository->method('findByEmail')->willReturn($alerts);
        $basketRepository = $this->createStub(BasketRepository::class);
        $basketRepository->method('findBy')->willReturn($baskets);
        $downloadRepository = $this->createStub(ProductItemDownloadRepository::class);
        $downloadRepository->method('findByBasketIds')->willReturn($downloads);

        return new AccountDataProvider($stockAlertRepository, $downloadRepository, $basketRepository);
    }
}
