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
use Symfony\Component\Form\Form;
use \Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Entity\Crowdfunding;
use Knp\Component\Pager\PaginatorInterface;
use c975L\ShopBundle\Entity\CrowdfundingNews;
use c975L\ShopBundle\Form\ShopFormFactoryInterface;
use c975L\ShopBundle\Repository\CrowdfundingRepository;
use c975L\ShopBundle\Repository\CrowdfundingMediaRepository;

class CrowdfundingService implements CrowdfundingServiceInterface
{
    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly CrowdfundingMediaRepository $crowdfundingMediaRepository,
        private readonly PaginatorInterface $paginator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopFormFactoryInterface $shopFormFactory,
    ) {
    }

    // Add news
    public function addNews(Crowdfunding $crowdfunding, CrowdfundingNews $news): void
    {
        $news->setCrowdfunding($crowdfunding);
        $news->setCreation(new DateTimeImmutable());
        $news->setModification(new DateTimeImmutable());
        $news->setPublishedDate(new DateTimeImmutable());

        $this->entityManager->persist($news);
        $this->entityManager->flush();
    }

    // Creates form
    public function createForm(string $name, $object): Form
    {
        return $this->shopFormFactory->create($name, $object);
    }

    // Finds all
    public function findAll()
    {
        return $this->crowdfundingRepository->findAll();
    }

    // Finds all sorted by position
    public function findAllSorted()
    {
        return $this->crowdfundingRepository->findAllSorted();
    }

    // Finds one by id
    public function findOneById(int $id): ?Crowdfunding
    {
        return $this->crowdfundingRepository->findOneById($id);
    }
}
