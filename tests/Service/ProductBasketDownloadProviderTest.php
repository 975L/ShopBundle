<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\PaymentBundle\Entity\Basket;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Service\ProductBasketDownloadProvider;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// What the customer area is handed for a paid basket: the copies its delivery already made, for as long as they live, and nothing at all once they are spent - the page promising exactly what the email did
class ProductBasketDownloadProviderTest extends TestCase
{
    private const string FILE = 'items/the-book-a1b2c3d4.pdf';

    /**
     * @param array<int, ProductItemDownload> $live item id => the link still working, as the service reads them
     */
    private function createProvider(array $live = []): ProductBasketDownloadProvider
    {
        $itemDownloadService = $this->createStub(ProductItemDownloadServiceInterface::class);
        $itemDownloadService->method('getFileItems')->willReturnCallback(
            static fn (array $items): array => [] === $items ? [] : [7 => ['title' => 'The book (PDF)', 'file' => self::FILE, 'size' => 1048576]]
        );
        $itemDownloadService->method('liveByItem')->willReturn($live);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters): string => '/shop/download/' . $parameters['token']
        );

        return new ProductBasketDownloadProvider($itemDownloadService, $urlGenerator);
    }

    private function basket(bool $holdsAFile = true): Basket
    {
        return new Basket()->setItems($holdsAFile ? ['product' => [7 => []]] : []);
    }

    private function download(string $token, string $expiresAt): ProductItemDownload
    {
        return new ProductItemDownload()
            ->setToken($token)
            ->setProductItemId(7)
            ->setExpiresAt(new \DateTimeImmutable($expiresAt));
    }

    public function testALinkStillValidIsHandedOutRatherThanCopiedAgain(): void
    {
        $expiresAt = new \DateTimeImmutable('+3 days');
        $downloads = $this->createProvider([7 => $this->download('live00000token00', $expiresAt->format('c'))])->getDownloads($this->basket());

        $this->assertCount(1, $downloads);
        $this->assertSame('The book (PDF)', $downloads[0]['title']);
        $this->assertSame('/shop/download/live00000token00', $downloads[0]['url']);
        $this->assertSame(1048576, $downloads[0]['size']);
        $this->assertSame($expiresAt->format('Y-m-d'), $downloads[0]['expiresAt']->format('Y-m-d'));
    }

    // The one thing this page must never do: minting here would hand the file out for as long as the shop kept selling it, while the email says the link expires and the nightly purge takes the copy away
    public function testAnItemWithoutALiveLinkIsLeftOutRatherThanCopiedAgain(): void
    {
        $itemDownloadService = $this->createMock(ProductItemDownloadServiceInterface::class);
        $itemDownloadService->method('getFileItems')->willReturn([7 => ['title' => 'The book (PDF)', 'file' => self::FILE, 'size' => 1048576]]);
        $itemDownloadService->method('liveByItem')->willReturn([]);
        $itemDownloadService->expects($this->never())->method('prepareFileForDownload');

        $provider = new ProductBasketDownloadProvider($itemDownloadService, $this->createStub(UrlGeneratorInterface::class));

        $this->assertSame([], $provider->getDownloads($this->basket()));
    }

    public function testABasketHoldingNoFileAsksForNothing(): void
    {
        $this->assertSame([], $this->createProvider()->getDownloads($this->basket(false)));
    }
}
