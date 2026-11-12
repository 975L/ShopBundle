<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Enum\BasketStatus;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Controller\ProductItemDownloadController;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use c975L\UiBundle\Service\AgeVerifier;
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
        bool $gateClosed = false,
        BasketStatus $status = BasketStatus::Paid,
    ): ProductItemDownloadController {
        $ageVerifier = $this->createStub(AgeVerifier::class);
        $ageVerifier->method('isGateClosed')->willReturn($gateClosed);
        $basketRepository = $this->createStub(BasketRepository::class);
        $basketRepository->method('find')->willReturn(new Basket()->setStatus($status));
        $controller = new ProductItemDownloadController($downloadService, $responseFactory, $ageVerifier, $this->createStub(ProductItemRepository::class), $basketRepository);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $template): string => $template);
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
        $this->assertSame('@c975LShop/product/item_downloaded.html.twig', $response->getContent());
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

    // A product for adults: the gate is drawn, the link left unspent and the file never served until the visitor is verified
    public function testAProductForAdultsWaitsBehindTheAgeGate(): void
    {
        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->method('resolveFilePath')->willReturn('/tmp/private/medias/shop/items/invoice-abc123.pdf');
        $downloadService->expects($this->never())->method('recordDownloaded');
        $responseFactory = $this->createMock(PrivateFileResponseFactoryInterface::class);
        $responseFactory->expects($this->never())->method('createDownloadResponse');

        $response = $this->createController($downloadService, $responseFactory, true)->download($this->createDownload());

        $this->assertSame('@c975LShop/product/item_age_gate.html.twig', $response->getContent());
    }

    // A dead link of a product for adults says so at once, no verification asked for a file there is no longer to serve
    public function testAnExpiredLinkOfAProductForAdultsSkipsTheAgeGate(): void
    {
        $downloadService = $this->createStub(ProductItemDownloadServiceInterface::class);
        $downloadService->method('resolveFilePath')->willReturn(null);

        $response = $this->createController($downloadService, $this->createStub(PrivateFileResponseFactoryInterface::class), true)->download($this->createDownload());

        $this->assertSame('@c975LShop/product/item_downloaded.html.twig', $response->getContent());
    }

    // A refunded order no longer hands anything over, a link it was sent before the refund included
    public function testALinkOfARefundedOrderServesNothing(): void
    {
        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->expects($this->never())->method('resolveFilePath');
        $downloadService->expects($this->never())->method('recordDownloaded');

        $response = $this->createController($downloadService, $this->createStub(PrivateFileResponseFactoryInterface::class), status: BasketStatus::Refunded)->download($this->createDownload());

        $this->assertSame('@c975LShop/product/item_downloaded.html.twig', $response->getContent());
    }
}
