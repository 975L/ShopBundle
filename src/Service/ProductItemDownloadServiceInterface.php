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
    /**
     * The items of a basket carrying a bought file, keyed by the id of the item, so the two readers of
     * that basket - the email sent on payment and the customer area - walk it the same way.
     *
     * @param array<string, array<int, array<string, mixed>>> $basketItems the shape PaymentBundle's Basket::getItems() holds
     *
     * @return array<int, array{title: string, file: string, size: int|null}>
     */
    public function getFileItems(array $basketItems): array;

    /**
     * Copies a bought digital file out of the private directory into the download one, behind a token
     * the buyer's emailed link carries (the links are then emailed by ProductItemDownloadMessageHandler).
     * The copy stays out of reach of the web server: the route hands it over, nothing else.
     *
     * @param string $sourceFilePath the file's path relative to the private directory
     *
     * @return string|null the generated download token, valid for ProductItemDownloadService::VALIDITY_DAYS
     *                     days - null when the source file is gone from the private directory, nothing
     *                     having been copied or recorded
     */
    public function prepareFileForDownload(int $basketId, int $productItemId, string $sourceFilePath): ?string;

    /**
     * Gives the absolute path of the copy the token stands for.
     *
     * @return string|null null when the link has expired or its copy is gone, the caller showing the
     *                     explanatory page rather than a download
     */
    public function resolveFilePath(ProductItemDownload $productItemDownload): ?string;

    /**
     * The links of a basket whose copy is still on the disk and whose link still works, so a caller
     * handing out that basket's files reuses them rather than copying the same file over again - which
     * is what keeps a retried email, or a second visit to the customer area, from piling up copies.
     *
     * @return array<int, ProductItemDownload> item id => the newest link still working
     */
    public function liveByItem(int $basketId): array;

    /**
     * Deletes the copies whose link is spent, then the rows kept past that to explain themselves.
     *
     * @return int the number of copies deleted
     */
    public function purgeExpired(): int;

    // Records that the buyer actually downloaded the file.
    public function recordDownloaded(ProductItemDownload $productItemDownload): void;
}
