<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class ProductItemDownloadController extends AbstractController
{
    public function __construct(
        private readonly ProductItemDownloadServiceInterface $productItemDownloadService,
        private readonly PrivateFileResponseFactoryInterface $privateFileResponseFactory,
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
        // An expired link, or one whose file the shop has since removed, gets the page saying so rather than a download recorded against nothing
        $filePath = $this->productItemDownloadService->resolveFilePath($productItemDownload);
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
