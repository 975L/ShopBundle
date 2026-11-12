<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\MessageHandler;

use c975L\PaymentBundle\Email\BasketEmailSender;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Enum\BasketStatus;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use c975L\ShopBundle\MessageHandler\ProductItemDownloadMessageHandler;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The message is handled after the payment, sometimes long after: what the basket has become since decides whether a file is copied and sent
class ProductItemDownloadMessageHandlerTest extends TestCase
{
    // A basket refunded before its message was handled is owed nothing
    public function testARefundedBasketGetsNoCopyAndNoEmail(): void
    {
        $basketRepository = $this->createStub(BasketRepository::class);
        $basketRepository->method('find')->willReturn(new Basket()->setStatus(BasketStatus::Refunded));

        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->expects($this->never())->method('getFileItems');

        $emailSender = $this->createMock(BasketEmailSender::class);
        $emailSender->expects($this->never())->method('send');

        $handler = new ProductItemDownloadMessageHandler($basketRepository, $emailSender, $downloadService, $this->createStub(UrlGeneratorInterface::class));
        $handler(new ProductItemDownloadMessage(7));
    }

    // A paid basket holding no file sends nothing either, but is read
    public function testAPaidBasketIsRead(): void
    {
        $basketRepository = $this->createStub(BasketRepository::class);
        $basketRepository->method('find')->willReturn(new Basket()->setStatus(BasketStatus::Paid));

        $downloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $downloadService->method('liveByItem')->willReturn([]);
        $downloadService->expects($this->once())->method('getFileItems')->willReturn([]);

        $handler = new ProductItemDownloadMessageHandler($basketRepository, $this->createStub(BasketEmailSender::class), $downloadService, $this->createStub(UrlGeneratorInterface::class));
        $handler(new ProductItemDownloadMessage(7));
    }
}
