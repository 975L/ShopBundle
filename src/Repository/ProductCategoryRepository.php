<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\ProductCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCategory>
 *
 * @method ProductCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCategory[]    findAll()
 * @method ProductCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    public function findOneBySlug(string $slug): ?ProductCategory
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}