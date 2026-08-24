<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Entity\Basket;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ProductItemDownloadService implements ProductItemDownloadServiceInterface
{
    // How long the emailed link works, and how long the spent copy waits before it and its row go
    public const int VALIDITY_DAYS = 7;
    public const int RETENTION_DAYS = 30;

    private readonly string $privateDir;
    private readonly string $downloadDir;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductItemDownloadRepository $downloadRepository,
        private readonly Filesystem $filesystem,
    ) {
        $this->privateDir = $this->parameterBag->get('kernel.project_dir') . '/private/';
        $this->downloadDir = $this->privateDir . 'downloads/';
    }

    public function getFileItems(array $basketItems): array
    {
        $fileItems = [];

        foreach ($basketItems['product'] ?? [] as $id => $item) {
            if (empty($item['item']['file'])) {
                continue;
            }

            $fileItems[(int) $id] = [
                'title' => $item['parent']['title'] . ' (' . $item['item']['title'] . ')',
                'file' => $item['item']['file'],
                'size' => $item['item']['size'] ?? null,
            ];
        }

        return $fileItems;
    }

    public function prepareFileForDownload(int $basketId, int $productItemId, string $sourceFilePath): ?string
    {
        // Nothing to hand out when the bought file is gone from the private directory - the caller skips that item rather than emailing a link to a copy that was never made
        $sourcePath = $this->privateDir . $sourceFilePath;
        if (!$this->filesystem->exists($sourcePath)) {
            return null;
        }

        // The copy is the point: the link addresses a file of its own, so replacing or removing the original never breaks a link already sold, and two buyers never share one
        $token = bin2hex(random_bytes(8));
        $targetFilename = $this->buildFilename($sourceFilePath, $token);
        $this->filesystem->copy($sourcePath, $this->downloadDir . $targetFilename);

        // Records the download in the database
        $download = new ProductItemDownload();
        $download->setBasketId($basketId);
        $download->setProductItemId($productItemId);
        $download->setToken($token);
        $download->setFilename($targetFilename);
        $download->setExpiresAt(new \DateTimeImmutable('+' . self::VALIDITY_DAYS . ' days'));
        $download->setDownloaded(false);

        $this->entityManager->persist($download);
        $this->entityManager->flush();

        return $token;
    }

    public function resolveFilePath(ProductItemDownload $productItemDownload): ?string
    {
        // An expired link resolves to nothing, whatever its copy still does on the disk
        if ($productItemDownload->getExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        $path = $this->downloadDir . $productItemDownload->getFilename();

        return $this->filesystem->exists($path) ? $path : null;
    }

    public function purgeExpired(): int
    {
        $now = new \DateTimeImmutable();

        // The copy goes as soon as the link is spent, downloaded or not: waiting for a click that may never come would keep it on the disk forever
        $deleted = 0;
        foreach ($this->downloadRepository->findExpired($now) as $download) {
            $path = $this->downloadDir . $download->getFilename();
            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
                ++$deleted;
            }
        }

        // The rows outlive their copy long enough to keep telling a late buyer why the link no longer works, then go too
        $this->downloadRepository->deleteExpiredBefore($now->modify('-' . self::RETENTION_DAYS . ' days'));

        return $deleted;
    }

    /**
     * @return array<int, ProductItemDownload> item id => the newest link still working
     */
    public function liveByItem(int $basketId): array
    {
        $live = [];

        foreach ($this->downloadRepository->findLiveByBasket($basketId, new \DateTimeImmutable()) as $download) {
            $itemId = $download->getProductItemId();

            // Rows are read newest first, so the first one met for an item is the one to reuse - and only if its copy is still on the disk
            if (null === $itemId || isset($live[$itemId]) || null === $this->resolveFilePath($download)) {
                continue;
            }

            $live[$itemId] = $download;
        }

        return $live;
    }

    // Records the downloaded in the database
    public function recordDownloaded(ProductItemDownload $productItemDownload): void
    {
        $productItemDownload->setDownloaded(true);
        $productItemDownload->setDownloadedAt(new \DateTimeImmutable());
        $this->entityManager->persist($productItemDownload);

        $basket = $this->entityManager->getRepository(Basket::class)->find($productItemDownload->getBasketId());

        // The row may be gone while the copy is still served: the download is recorded all the same
        if (null !== $basket) {
            $basket->setDownloaded(new \DateTime());
            $basket->setModification(new \DateTime());
            $this->entityManager->persist($basket);
        }

        $this->entityManager->flush();
    }

    // Hands the copy the buyer's own name: the source's trailing hash gives way to the token, so the file says which purchase it belongs to
    private function buildFilename(string $sourceFilePath, string $token): string
    {
        $fileInfo = new \SplFileInfo($sourceFilePath);
        $extension = $fileInfo->getExtension();
        $filenameWithoutExt = $fileInfo->getBasename('.' . $extension);
        $lastHyphenPos = strrpos($filenameWithoutExt, '-');
        $baseFilename = false !== $lastHyphenPos ? substr($filenameWithoutExt, 0, $lastHyphenPos) : $filenameWithoutExt;

        return $baseFilename . '-' . $token . '.' . $extension;
    }
}
