<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItemDownload;

interface ProductItemDownloadServiceInterface
{
    public function prepareFileForDownload(int $basketId, int $productItemId, string $sourceFilePath): string;

    public function recordDownloaded(ProductItemDownload $productItemDownload): void;
}
