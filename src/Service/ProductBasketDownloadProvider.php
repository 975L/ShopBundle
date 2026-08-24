<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Contract\BasketDownloadProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Hands the files of a paid basket back to their buyer from PaymentBundle's customer area, the very links their email carries and for exactly as long. The basket is already checked as paid and as belonging to whoever asks, so nothing is re-checked here
class ProductBasketDownloadProvider implements BasketDownloadProviderInterface
{
    public function __construct(
        private readonly ProductItemDownloadServiceInterface $itemDownloadService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getDownloads(Basket $basket): array
    {
        $fileItems = $this->itemDownloadService->getFileItems($basket->getItems());
        if ([] === $fileItems) {
            return [];
        }

        $live = $this->itemDownloadService->liveByItem((int) $basket->getId());
        $downloads = [];

        foreach ($fileItems as $id => $fileItem) {
            // Only the copies the delivery already made, never a fresh one: minting here would hand out a file for as long as the shop kept selling it, while its email says the link expires and the nightly purge takes the copy away - one promise, one lifetime, one process (see ProductItemDownloadMessageHandler)
            $download = $live[$id] ?? null;
            if (null === $download) {
                continue;
            }

            $downloads[] = [
                'title' => $fileItem['title'],
                'url' => $this->urlGenerator->generate('shop_download', ['token' => $download->getToken()]),
                'size' => $fileItem['size'],
                'expiresAt' => $download->getExpiresAt(),
            ];
        }

        return $downloads;
    }
}
