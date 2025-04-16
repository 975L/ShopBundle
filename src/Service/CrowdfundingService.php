<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Repository\CrowdfundingRepository;
use c975L\ShopBundle\Repository\CrowdfundingMediaRepository;
use Knp\Component\Pager\PaginatorInterface;
use \Doctrine\ORM\EntityManagerInterface;
class CrowdfundingService implements CrowdfundingServiceInterface
{
    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly CrowdfundingMediaRepository $crowdfundingMediaRepository,
        private readonly PaginatorInterface $paginator,
        private readonly EntityManagerInterface $entityManager,
    ) {
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
}
