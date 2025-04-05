<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use SplFileInfo;
use DateTimeImmutable;
use c975L\ShopBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use c975L\ShopBundle\Entity\ProductItemDownload;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProductItemDownloadService implements ProductItemDownloadServiceInterface
{
    private string $privateDir;
    private string $downloadDir;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->privateDir = $this->parameterBag->get('kernel.project_dir') . '/private/';
        $this->downloadDir = $this->parameterBag->get('kernel.project_dir') . '/public/downloads/';
    }

    public function prepareFileForDownload(int $basketId, int $productItemId, string $sourceFilePath): string
    {
        $filesystem = new Filesystem();

        // Creates folder
        if (!$filesystem->exists($this->downloadDir)) {
            $filesystem->mkdir($this->downloadDir);
        }

        // Copy file
        $sourcePath = $this->privateDir . $sourceFilePath;
        if ($filesystem->exists($sourcePath)) {
            $token = bin2hex(random_bytes(8));

            $fileInfo = new SplFileInfo($sourceFilePath);
            $extension = $fileInfo->getExtension();
            $filenameWithoutExt = $fileInfo->getBasename('.' . $extension);
            $lastHyphenPos = strrpos($filenameWithoutExt, '-');
            if ($lastHyphenPos !== false) {
                $baseFilename = substr($filenameWithoutExt, 0, $lastHyphenPos);
                $targetFilename = $baseFilename . '-' . $token . '.' . $extension;
            } else {
                $targetFilename = $filenameWithoutExt . '-' . $token . '.' . $extension;
            }
            $targetPath = $this->downloadDir . $targetFilename;
            $filesystem->copy($sourcePath, $targetPath);

            // Records the download in the database
            $download = new ProductItemDownload();
            $download->setBasketId($basketId);
            $download->setToken($token);
            $download->setFilename($targetFilename);
            $download->setExpiresAt(new DateTimeImmutable('+7 days'));
            $download->setDownloaded(false);

            $this->entityManager->persist($download);
            $this->entityManager->flush();

        }

        return $token;
    }

    // Records the downloaded in the database
    public function recordDownloaded(ProductItemDownload $productItemDownload): void
    {
        $productItemDownload->setDownloaded(true);
        $productItemDownload->setDownloadedAt(new DateTimeImmutable());
        $this->entityManager->persist($productItemDownload);

        $basket = $this->entityManager->getRepository(Basket::class)->findOneById($productItemDownload->getBasketId());
        $basket->setDownloaded(new DateTimeImmutable());
        $basket->setModification(new DateTimeImmutable());

        $this->entityManager->persist($basket);
        $this->entityManager->flush();
    }
}