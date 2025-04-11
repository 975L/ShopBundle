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
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Repository\CrowdfundingCounterpartRepository;
use c975L\ShopBundle\Repository\CrowdfundingCounterpartMediaRepository;

class CrowdfundingCounterpartService implements CrowdfundingCounterpartServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CrowdfundingCounterpartRepository $crowdfundingCounterpartRepository,
        private readonly CrowdfundingCounterpartMediaRepository $crowdfundingCounterpartMediaRepository
    ) {
    }

    // Finds one by id
    public function findOneById(int $id): CrowdfundingCounterpart
    {
        return $this->crowdfundingCounterpartRepository->findOneById($id);
    }

    // Finds all medias
    public function findAllMedias()
    {
        return $this->crowdfundingCounterpartMediaRepository->findAll();
    }

    // Deletes one media by name
    public function deleteOneMediaByName(string $name): void
    {
        $this->deleteCrowdfundingCounterpartMedia($this->crowdfundingCounterpartMediaRepository->findOneByName($name));

        $this->entityManager->flush();
    }

    // Deletes CrowdfundingCounterpartMedia/File
    public function deleteCrowdfundingCounterpartMedia($media): void
    {
        if ($media) {
            // Not linked to CrowdfundingCounterpart
            if (null === $media->getCrowdfundingCounterpart()) {
                $this->entityManager->remove($media);
            // Not deleted, see CrowdfundingCounterpartListener->prePersist()
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
