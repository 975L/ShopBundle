<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;

class MediaService implements MediaServiceInterface
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Finds all files
    public function findAll(): array
    {
        return $this->mediaRepository->findAll();
    }

    // Updates database for not existing media
    public function updateDatabaseByName(string $file): void
    {
        $media = $this->mediaRepository->findOneBy(['name' => $file]);
        $media->setName(null);
        $media->setSize(null);
        $media->setPosition(null);
        $media->setUser(null);
        $media->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($media);
        $this->entityManager->flush();
    }
}
