<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\PaymentBundle\Email\BasketEmailSender;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\Service\ProductItemDownloadService;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class ProductItemDownloadMessageHandler
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly BasketEmailSender $basketEmailSender,
        private readonly ProductItemDownloadServiceInterface $itemDownloadService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ProductItemDownloadMessage $message): void
    {
        $basket = $this->basketRepository->find($message->getBasketId());

        if (!$basket) {
            return;
        }

        // Process all product items in the basket
        $live = $this->itemDownloadService->liveByItem((int) $basket->getId());
        $downloadLinks = [];
        foreach ($this->itemDownloadService->getFileItems($basket->getItems()) as $id => $fileItem) {
            // A copy still valid is reused rather than made again, and the customer area hands out these very links, so the page and the email never promise two different things (see ProductBasketDownloadProvider)
            $token = isset($live[$id])
                ? $live[$id]->getToken()
                : $this->itemDownloadService->prepareFileForDownload((int) $basket->getId(), $id, $fileItem['file']);

            // The file is gone from the private directory: skip that item rather than emailing a link to a copy that was never made
            if (null === $token) {
                continue;
            }

            // The address is built here and not in the email template: the route belongs to this bundle, PaymentBundle only laying the links out (see BasketDownloadProviderInterface)
            $downloadLinks[$id] = [
                'title' => $fileItem['title'],
                'url' => $this->urlGenerator->generate('shop_download', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
                'size' => $fileItem['size'],
            ];
        }

        // Sends the email with download links, throwing on failure so Messenger retries it: the copies are already made and the buyer has no other way of reaching them
        if (!empty($downloadLinks)) {
            $sent = $this->basketEmailSender->send($basket, 'label.download_information', 'download_information', [
                'downloadLinks' => $downloadLinks,
                'expiration_days' => ProductItemDownloadService::VALIDITY_DAYS,
            ]);

            if (!$sent) {
                throw new \RuntimeException(sprintf('Could not send the download links of basket "%s": %s', $basket->getNumber(), $this->basketEmailSender->getLastError() ?? 'unknown error'));
            }
        }
    }
}
