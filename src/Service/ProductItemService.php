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
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Repository\ProductItemMediaRepository;

class ProductItemService implements ProductItemServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductItemRepository $productItemRepository,
        private readonly ProductItemMediaRepository $productItemMediaRepository
    ) {
    }

    // Finds one by id
    public function findOneById(int $id): ProductItem
    {
        return $this->productItemRepository->findOneById($id);
    }
}
