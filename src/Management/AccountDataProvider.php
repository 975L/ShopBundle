<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Account\AccountDataProviderInterface;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;

// The shop part of a member's data export (ConfigBundle's /account/export): the stock alerts of their address and the download links of their orders, the orders themselves coming from PaymentBundle
class AccountDataProvider implements AccountDataProviderInterface
{
    public function __construct(
        private readonly ProductItemStockAlertRepository $stockAlertRepository,
        private readonly ProductItemDownloadRepository $downloadRepository,
        private readonly BasketRepository $basketRepository,
    ) {
    }

    // Under "stock_alerts" and "downloads", each left out when empty
    public function getAccountData(UserInterface $user): array
    {
        $email = $user instanceof InactivityAwareInterface ? $user->getEmail() : null;
        $alerts = null === $email ? [] : $this->stockAlertRepository->findByEmail($email);
        $baskets = $this->basketRepository->findBy(['user' => $user]);
        $numbers = [];
        foreach ($baskets as $basket) {
            $numbers[(int) $basket->getId()] = $basket->getNumber();
        }
        $downloads = $this->downloadRepository->findByBasketIds(array_keys($numbers));

        return array_filter([
            'stock_alerts' => array_map($this->alert(...), $alerts),
            'downloads' => array_map(static fn (ProductItemDownload $download): array => [
                'order' => $numbers[$download->getBasketId()] ?? null,
                'file' => $download->getFilename(),
                'expires' => $download->getExpiresAt(),
                'downloaded' => $download->getDownloadedAt(),
            ], $downloads),
        ]);
    }

    // One alert as the member reads it
    /** @return array<string, mixed> */
    private function alert(ProductItemStockAlert $alert): array
    {
        return [
            'item' => $alert->getProductItem()?->getTitle(),
            'product' => $alert->getProductItem()?->getProduct()?->getTitle(),
            'date' => $alert->getCreatedAt(),
            'notified' => $alert->getNotifiedAt(),
        ];
    }
}
