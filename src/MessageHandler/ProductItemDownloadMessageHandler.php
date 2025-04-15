<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use c975L\ShopBundle\Service\EmailServiceInterface;

#[AsMessageHandler]
class ProductItemDownloadMessageHandler
{
    public function __construct(
        private BasketRepository $basketRepository,
        private readonly EmailServiceInterface $emailService,
        private ProductItemDownloadServiceInterface $itemDownloadService
    ) {}

    public function __invoke(ProductItemDownloadMessage $message): void
    {
        $basket = $this->basketRepository->findOneById($message->getBasketId());

        if (!$basket) {
            return;
        }

        // Process all product items in the basket
        $downloadLinks = [];
        foreach ($basket->getItems() as $id => $item) {
            if (!empty($item['item']['file'])) {
                $token = $this->itemDownloadService->prepareFileForDownload(
                    $basket->getId(),
                    $id,
                    $item['item']['file']
                );

                $downloadLinks[$id] = [
                    'title' => $item['product']['title'] . ' (' . $item['item']['title'] . ')',
                    'token' => $token,
                    'size' => $item['item']['size'],
                ];
            }
        }

        // Sends the email with download links
        if (!empty($downloadLinks)) {
            $this->emailService->sendDownloadInformation($basket, $downloadLinks);
        }
    }
}