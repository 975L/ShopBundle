<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\ShopBundle\Controller\ProductItemDownloadController;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Twig\Environment;

class ProductItemDownloadControllerTest extends TestCase
{
    private function createDownload(): ProductItemDownload
    {
        return new ProductItemDownload()
            ->setBasketId(1)
            ->setToken('abcdefgh12345678')
            ->setFilename('medias/shop/items/invoice-abc123.pdf')
            ->setExpiresAt(new \DateTimeImmutable('+7 days'));
    }

    private function createController(
        ProductItemDownloadServiceInterface $downloadService,
        PrivateFileResponseFactoryInterface $responseFactory,
    ): ProductItemDownloadController {
        $controller = new ProductItemDownloadController($downloadService, $responseFactory);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<downloaded>');
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        return $controller;
    }

    // The file is served straight from the private directory, never from a copy the web server could hand out on its own
    public function testDownloadServesThePathTheServiceResolvesForTheToken(): void
    {
        $download = $this->createDownload();

        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->expects($this->once())->method('resolveFilePath')->with($download)->willReturn('/tmp/private/medias/shop/items/invoice-abc123.pdf');

        $response = $this->createStub(BinaryFileResponse::class);
        $responseFactory = $this->createMock(PrivateFileResponseFactoryInterface::class);
        $responseFactory->expects($this->once())
            ->method('createDownloadResponse')
            ->with('/tmp/private/medias/shop/items/invoice-abc123.pdf', 'invoice-abc123.pdf')
            ->willReturn($response);

        $result = $this->createController($downloadService, $responseFactory)->download($download);

        $this->assertSame($response, $result);
    }

    public function testDownloadRecordsTheDownloadOnlyOnceTheResponseIsBuilt(): void
    {
        $download = $this->createDownload();

        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->method('resolveFilePath')->willReturn('/tmp/private/medias/shop/items/invoice-abc123.pdf');
        $downloadService->expects($this->once())->method('recordDownloaded')->with($download);

        $responseFactory = $this->createStub(PrivateFileResponseFactoryInterface::class);
        $responseFactory->method('createDownloadResponse')->willReturn($this->createStub(BinaryFileResponse::class));

        $this->createController($downloadService, $responseFactory)->download($download);
    }

    // An expired link, or one whose file is gone, shows an explanatory page instead of a 404
    public function testDownloadRendersTheExplanatoryPageWhenTheServiceResolvesNothing(): void
    {
        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->method('resolveFilePath')->willReturn(null);
        $downloadService->expects($this->never())->method('recordDownloaded');

        $responseFactory = $this->createMock(PrivateFileResponseFactoryInterface::class);
        $responseFactory->expects($this->never())->method('createDownloadResponse');

        $response = $this->createController($downloadService, $responseFactory)->download($this->createDownload());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<downloaded>', $response->getContent());
    }

    // Nothing is recorded against a file the factory could not open either
    public function testDownloadRendersTheExplanatoryPageWhenTheFactoryReturnsNothing(): void
    {
        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->method('resolveFilePath')->willReturn('/tmp/private/medias/shop/items/invoice-abc123.pdf');
        $downloadService->expects($this->never())->method('recordDownloaded');

        $responseFactory = $this->createStub(PrivateFileResponseFactoryInterface::class);
        $responseFactory->method('createDownloadResponse')->willReturn(null);

        $response = $this->createController($downloadService, $responseFactory)->download($this->createDownload());

        $this->assertSame(200, $response->getStatusCode());
    }
}
