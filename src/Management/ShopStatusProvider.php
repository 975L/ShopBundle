<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\StatusProviderInterface;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Repository\MediaRepository;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;

// The four counts of a catalog that is published but not finished - a sheet with no picture, a description too thin to rank, a picture with no alternative text, a category page with nothing of its own to say - each naming something to go and fix, where a plain count of products would not; the order backlog and the stalled payments are PaymentStatusProvider's, all three being read off Basket
class ShopStatusProvider implements StatusProviderInterface
{
    // Roughly the 150 to 200 words a product description needs to rank and to reassure, counted in characters because that is what a database can count without loading the catalog. The rich text markup counts too, so a description barely over the line is missed rather than reported - the count is there to name obvious gaps, not to grade prose
    private const int THIN_DESCRIPTION_CHARS = 900;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
    ) {
    }

    public function getStatusKey(): string
    {
        return 'shop';
    }

    public function getStatusData(): array
    {
        return [
            'productsWithoutImage' => $this->productsWithoutImage(),
            'productsWithThinDescription' => $this->productsWithThinDescription(),
            'mediasWithoutAlt' => $this->mediasWithoutAlt(),
            'categoriesWithoutDescription' => $this->categoriesWithoutDescription(),
        ];
    }

    // A sheet with no picture, which is the one thing a listing shows of a product
    private function productsWithoutImage(): int
    {
        return (int) $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.medias IS EMPTY')
            ->andWhere('p.isDeleted = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // A sheet nobody can rank, its description too short to say anything - a missing one counted with them, LENGTH(NULL) answering NULL rather than zero
    private function productsWithThinDescription(): int
    {
        return (int) $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.description IS NULL OR LENGTH(p.description) < :chars')
            ->andWhere('p.isDeleted = false')
            ->setParameter('chars', self::THIN_DESCRIPTION_CHARS)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // The alternative text of a picture, which is what a search engine and a screen reader read of it - asked of the whole hierarchy so an item's picture counts like a product's, minus the downloaded files, which nobody ever sees
    private function mediasWithoutAlt(): int
    {
        return (int) $this->mediaRepository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m NOT INSTANCE OF :file')
            // The empty media a new item is created with (see ProductItemListener) carries no file, so there is no picture to write an alternative for
            ->andWhere('m.name IS NOT NULL')
            ->andWhere("m.alt IS NULL OR m.alt = ''")
            ->setParameter('file', ProductItemFile::class)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // A category page with no description of its own carries no meta description either, its two being the same column
    private function categoriesWithoutDescription(): int
    {
        return (int) $this->productCategoryRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.description IS NULL')
            ->orWhere("c.description = ''")
            ->getQuery()
            ->getSingleScalarResult();
    }
}
