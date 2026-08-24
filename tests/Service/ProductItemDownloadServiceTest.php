<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use c975L\ShopBundle\Service\ProductItemDownloadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

// What a bought file owes the buyer, and what it must never owe anyone else: a copy of its own, named after the purchase, kept out of reach of the web server
class ProductItemDownloadServiceTest extends TestCase
{
    private const string SOURCE = 'medias/shop/items/story-6986069bc7507.pdf';

    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/shop-download-' . bin2hex(random_bytes(4));
        $this->filesystem->dumpFile($this->projectDir . '/private/' . self::SOURCE, 'pdf');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    private function createService(
        ?EntityManagerInterface $entityManager = null,
        ?ProductItemDownloadRepository $repository = null,
    ): ProductItemDownloadService {
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        return new ProductItemDownloadService(
            $parameterBag,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $repository ?? $this->createStub(ProductItemDownloadRepository::class),
            $this->filesystem,
        );
    }

    private function createDownload(string $filename, string $expiresAt): ProductItemDownload
    {
        return new ProductItemDownload()
            ->setBasketId(1)
            ->setToken('abcdefgh12345678')
            ->setFilename($filename)
            ->setExpiresAt(new \DateTimeImmutable($expiresAt));
    }

    // The copy is the point, and where it lands is the point too: only the route reaches it
    public function testPreparingADownloadCopiesTheFileUnderPrivateAndNowhereThatIsServed(): void
    {
        $token = $this->createService()->prepareFileForDownload(1, 2, self::SOURCE);

        $this->assertFileExists($this->projectDir . '/private/downloads/story-' . $token . '.pdf');
        $this->assertDirectoryDoesNotExist($this->projectDir . '/public/downloads');
        $this->assertFileExists($this->projectDir . '/private/' . self::SOURCE, 'the original must be left alone');
    }

    public function testPreparingADownloadNamesTheCopyAfterThePurchaseAndRecordsItForSevenDays(): void
    {
        $recorded = null;
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$recorded): void {
            $recorded = $entity;
        });

        $token = $this->createService($entityManager)->prepareFileForDownload(1, 2, self::SOURCE);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', (string) $token);
        $this->assertInstanceOf(ProductItemDownload::class, $recorded);
        $this->assertSame('story-' . $token . '.pdf', $recorded->getFilename(), 'the source hash gives way to the token');
        $this->assertFalse($recorded->isDownloaded());
        $this->assertSame(
            new \DateTimeImmutable('+' . ProductItemDownloadService::VALIDITY_DAYS . ' days')->format('Y-m-d'),
            $recorded->getExpiresAt()->format('Y-m-d')
        );
    }

    // Nothing to hand out, nothing copied, nothing recorded: the caller skips the item rather than emailing a dead link
    public function testPreparingADownloadRecordsNothingWhenTheSourceIsGone(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->assertNull($this->createService($entityManager)->prepareFileForDownload(1, 2, 'medias/shop/items/gone.pdf'));
        $this->assertDirectoryDoesNotExist($this->projectDir . '/private/downloads');
    }

    public function testAValidTokenResolvesToItsCopy(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf', 'pdf');

        $this->assertSame(
            $this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf',
            $this->createService()->resolveFilePath($this->createDownload('story-abcdefgh12345678.pdf', '+1 day'))
        );
    }

    // The route enforces the delay itself, rather than relying on the scheduled command having got there first
    public function testAnExpiredTokenResolvesToNothingEvenThoughItsCopyIsStillThere(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf', 'pdf');

        $this->assertNull($this->createService()->resolveFilePath($this->createDownload('story-abcdefgh12345678.pdf', '-1 second')));
    }

    public function testATokenWhoseCopyIsGoneResolvesToNothing(): void
    {
        $this->assertNull($this->createService()->resolveFilePath($this->createDownload('story-abcdefgh12345678.pdf', '+1 day')));
    }

    // What spares a second hand-out - a retried email, a return to the customer area - from copying the same file over again
    public function testLiveLinksReuseTheNewestOfEachItem(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-newest0000000.pdf', 'pdf');
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-older00000000.pdf', 'pdf');

        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findLiveByBasket')->willReturn([
            $this->createDownload('story-newest0000000.pdf', '+1 day')->setProductItemId(7)->setToken('newest0000000'),
            $this->createDownload('story-older00000000.pdf', '+1 day')->setProductItemId(7)->setToken('older00000000'),
        ]);

        $live = $this->createService(null, $repository)->liveByItem(1);

        $this->assertSame([7], array_keys($live));
        $this->assertSame('newest0000000', $live[7]->getToken());
    }

    // The rows made before the item was recorded against them carry no item id, so they cannot be matched to one
    public function testLiveLinksLeaveOutARowCarryingNoItemId(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf', 'pdf');

        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findLiveByBasket')->willReturn([$this->createDownload('story-abcdefgh12345678.pdf', '+1 day')]);

        $this->assertSame([], $this->createService(null, $repository)->liveByItem(1));
    }

    // A row may outlive its copy: the nightly clean-up deletes the file before the row explaining itself
    public function testLiveLinksLeaveOutARowWhoseCopyIsGone(): void
    {
        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findLiveByBasket')->willReturn([$this->createDownload('story-abcdefgh12345678.pdf', '+1 day')->setProductItemId(7)]);

        $this->assertSame([], $this->createService(null, $repository)->liveByItem(1));
    }

    // A copy whose link ran out goes, whether or not the buyer ever clicked it
    public function testPurgingDeletesTheCopyOfEveryExpiredLink(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf', 'pdf');
        $this->filesystem->dumpFile($this->projectDir . '/private/downloads/story-neverclicked00.pdf', 'pdf');

        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findExpired')->willReturn([
            $this->createDownload('story-abcdefgh12345678.pdf', '-1 day')->setDownloaded(true),
            $this->createDownload('story-neverclicked00.pdf', '-1 day'),
        ]);

        $this->assertSame(2, $this->createService(null, $repository)->purgeExpired());
        $this->assertFileDoesNotExist($this->projectDir . '/private/downloads/story-abcdefgh12345678.pdf');
        $this->assertFileDoesNotExist($this->projectDir . '/private/downloads/story-neverclicked00.pdf');
    }

    // The count is what was actually taken off the disk, a row whose copy already went adding nothing to it
    public function testPurgingCountsTheCopiesItActuallyDeleted(): void
    {
        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findExpired')->willReturn([$this->createDownload('story-alreadygone0.pdf', '-1 day')]);

        $this->assertSame(0, $this->createService(null, $repository)->purgeExpired());
    }

    // The rows outlive their copy long enough to keep explaining themselves, then go
    public function testPurgingDropsTheRowsExpiredPastTheRetention(): void
    {
        $repository = $this->createMock(ProductItemDownloadRepository::class);
        $repository->method('findExpired')->willReturn([]);
        $repository->expects($this->once())
            ->method('deleteExpiredBefore')
            ->with($this->callback(fn (\DateTimeImmutable $date): bool => $date->format('Y-m-d') === new \DateTimeImmutable('-' . ProductItemDownloadService::RETENTION_DAYS . ' days')->format('Y-m-d')));

        $this->createService(null, $repository)->purgeExpired();
    }
}
