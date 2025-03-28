<?php

namespace c975L\ShopBundle\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Entity\ProductItemDownload;
use Symfony\Component\Filesystem\Filesystem;
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

            $extension = pathinfo($sourceFilePath, PATHINFO_EXTENSION);
            $filenameWithoutExt = pathinfo($sourceFilePath, PATHINFO_FILENAME);
            $lastHyphenPos = strrpos($filenameWithoutExt, '-');
            if ($lastHyphenPos !== false) {
                $baseFilename = substr($filenameWithoutExt, 0, $lastHyphenPos);
                $targetFilename = $baseFilename . '-' . $token . '.' . $extension;
            } else {
                $targetFilename = $filenameWithoutExt . '-' . $token . '.' . $extension;
            }
            $targetPath = $this->downloadDir . $targetFilename;
            $filesystem->copy($sourcePath, $targetPath);

            // Enregistrer la relation dans la base de données
            $this->recordDownload($basketId, $productItemId, $token, $targetFilename);
        }

        return $token;
    }

    // Records the download in the database
    public function recordDownload(int $basketId, int $productItemId, string $token, string $filename): void
    {
        $download = new ProductItemDownload();
        $download->setBasketId($basketId);
        $download->setProductItemId($productItemId);
        $download->setToken($token);
        $download->setFilename($filename);
        $download->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $download->setDownloaded(false);

        $this->entityManager->persist($download);
        $this->entityManager->flush();
    }

    // Records the downloaded in the database
    public function recordDownloaded(ProductItemDownload $productItemDownload): void
    {
        $productItemDownload->setDownloaded(true);
        $productItemDownload->setDownloadedAt(new DateTimeImmutable());

        $this->entityManager->persist($productItemDownload);
        $this->entityManager->flush();
    }
}