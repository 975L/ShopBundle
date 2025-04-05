<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use DateTimeImmutable;
use c975L\ShopBundle\Entity\ProductItem;
use Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Repository\ProductItemFileRepository;
use c975L\ShopBundle\Repository\ProductItemMediaRepository;

class ProductItemService implements ProductItemServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductItemRepository $productItemRepository,
        private readonly ProductItemFileRepository $productItemFileRepository,
        private readonly ProductItemMediaRepository $productItemMediaRepository
    ) {
    }

    // Finds one by id
    public function findOneById(int $id): ProductItem
    {
        return $this->productItemRepository->findOneById($id);
    }

    // Finds all medias
    public function findAllMedias()
    {
        return $this->productItemMediaRepository->findAll();
    }

    // Finds all files
    public function findAllFiles()
    {
        return $this->productItemFileRepository->findAll();
    }

    // Deletes one media by name
    public function deleteOneMediaByName(string $name): void
    {
        $this->deleteProductItemMedia($this->productItemMediaRepository->findOneByName($name));
        $this->deleteProductItemMedia($this->productItemFileRepository->findOneByName($name));

        $this->entityManager->flush();
    }

    // Deletes ProductItemMedia/File
    public function deleteProductItemMedia($media): void
    {
        if ($media) {
            // Not linked to ProductItem
            if (null === $media->getProductItem()) {
                $this->entityManager->remove($media);
            // Not deleted, see ProductItemListener->prePersist()
            } else {
                $media->setName(null);
                $media->setUser(null);
                $media->setSize(null);
                $media->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($media);
            }
        }
    }
}
