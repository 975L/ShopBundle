<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use SplFileInfo;
use Symfony\Component\Routing\Attribute\Route;
use c975L\ShopBundle\Entity\ProductItemDownload;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProductItemDownloadController extends AbstractController
{
    public function __construct(
        private readonly ProductItemDownloadServiceInterface $productItemDownloadService,
        private readonly ParameterBagInterface $parameterBag
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
        $this->productItemDownloadService->recordDownloaded($productItemDownload);

        // Returns binary file
        $filename = $this->parameterBag->get('kernel.project_dir') . '/public/downloads/' . $productItemDownload->getFilename();
        if (file_exists($filename)) {
            $response = new BinaryFileResponse($filename);

            $fileInfo = new SplFileInfo($productItemDownload->getFilename());
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $fileInfo->getBasename('.' . $fileInfo->getExtension()) . '.' . $fileInfo->getExtension()
            );

            return $response;
        }

        return $this->render(
            '@c975LShop/product/item_downloaded.html.twig',
            [
                'productItem' => $productItemDownload,
            ]
        )->setMaxAge(3600);
    }
}
