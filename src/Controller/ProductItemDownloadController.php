<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use c975L\ShopBundle\Entity\ProductItemDownload;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
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
    public function download(ProductItemDownload $productItemDownload): BinaryFileResponse
    {
        $this->productItemDownloadService->recordDownloaded($productItemDownload);

        // Returns binary file
        $filename = $this->parameterBag->get('kernel.project_dir') . '/public/downloads/' . $productItemDownload->getFilename();
        $response = new BinaryFileResponse($filename);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            pathinfo($productItemDownload->getFilename(), PATHINFO_FILENAME) . '.' . pathinfo($productItemDownload->getFilename(), PATHINFO_EXTENSION)
        );

        return $response;
    }
}
