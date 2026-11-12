<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use c975L\UiBundle\Service\AgeVerifier;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class ProductItemDownloadController extends AbstractController
{
    public function __construct(
        private readonly ProductItemDownloadServiceInterface $productItemDownloadService,
        private readonly PrivateFileResponseFactoryInterface $privateFileResponseFactory,
        private readonly AgeVerifier $ageVerifier,
        private readonly ProductItemRepository $productItemRepository,
        private readonly BasketRepository $basketRepository,
    ) {
    }

    // DOWNLOAD
    #[Route(
        '/shop/download/{token:productItemDownload}',
        name: 'shop_download',
        requirements: ['token' => '[a-zA-Z0-9]{16}'],
        methods: ['GET']
    )]
    public function download(ProductItemDownload $productItemDownload)
    {
        // An expired link, one whose file the shop has since removed, or one of an order refunded since, gets the page saying so rather than a download recorded against nothing - what the customer area hands out read the same way (see PaymentBundle's isSettled())
        $basket = $this->basketRepository->find($productItemDownload->getBasketId());
        $filePath = true === $basket?->getStatus()->isSettled()
            ? $this->productItemDownloadService->resolveFilePath($productItemDownload)
            : null;

        // A product for adults waits behind the age gate, its link left unspent until the age verification found the visitor of age: the page reloads once they are, and the same link then serves the file - a dead link is said so without asking for a verification first
        if (null !== $filePath) {
            $age = $this->productItemRepository->find((int) $productItemDownload->getProductItemId())?->getProduct()?->getAge();
            if ($this->ageVerifier->isGateClosed($age)) {
                return $this->render('@c975LShop/product/item_age_gate.html.twig', ['age' => $age]);
            }
        }

        $response = null === $filePath
            ? null
            : $this->privateFileResponseFactory->createDownloadResponse($filePath, basename($filePath));

        if (null !== $response) {
            $this->productItemDownloadService->recordDownloaded($productItemDownload);

            return $response;
        }

        return $this->render(
            '@c975LShop/product/item_downloaded.html.twig',
            [
                'productItem' => $productItemDownload,
            ]
        );
    }
}
