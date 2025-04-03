<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItemDownload;

interface ProductItemDownloadServiceInterface
{
    public function prepareFileForDownload(int $basketId, int $productItemId, string $sourceFilePath): string;

    public function recordDownloaded(ProductItemDownload $productItemDownload): void;
}
