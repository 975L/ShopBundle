<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\Traits\BlockOwnerRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCategory>
 *
 * @method list<ProductCategory> findByBlockIds(array $blockIds)
 * @method ProductCategory|null  find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCategory|null  findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCategory[]     findAll()
 * @method ProductCategory[]     findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCategoryRepository extends ServiceEntityRepository
{
    use BlockOwnerRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function findOneBySlug(string $slug): ?ProductCategory
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    // The categories whose name holds the words, every one without them, in the back office's order, each with the number of its products left out of the trash and the templates
    /** @return list<array{0: ProductCategory, products: int|string}> */
    public function findWithProductCount(?string $search, int $limit): array
    {
        $builder = $this->createQueryBuilder('c')
            // A subquery rather than a GROUP BY, which a server under ONLY_FULL_GROUP_BY would refuse on the hydrated columns
            ->select('c, (SELECT COUNT(p.id) FROM ' . Product::class . ' p JOIN p.categories pc WHERE pc = c AND p.isDeleted = false AND p.template = false) AS products')
            ->orderBy('c.position', \SortDirection::Ascending)
            ->addOrderBy('c.name', \SortDirection::Ascending)
            ->setMaxResults($limit);
        if ('' !== trim((string) $search)) {
            $builder->andWhere('c.name LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_\\') . '%');
        }

        return $builder->getQuery()->getResult();
    }
}
