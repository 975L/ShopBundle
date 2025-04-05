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
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use c975L\ShopBundle\Entity\ProductItemDownload;
use c975L\ShopBundle\Repository\ProductRepository;

class ShopService implements ShopServiceInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator
    ) {
    }

    // Gets the products paginated
    public function findAllProductsPaginated($query)
    {
        return $this->paginator->paginate(
            $this->productRepository->findAllSorted(),
            $query->getInt('p', 1),
            9
        );
    }
}