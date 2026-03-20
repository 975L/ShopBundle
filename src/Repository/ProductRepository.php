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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ProductCategoryRepository $categoryRepository
    ) {
        parent::__construct($registry, Product::class);
    }

    // Finds products based on search
    public function search(string $query, ?string $categorySlug = null): array
    {
        if (empty($query)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :query OR p.description LIKE :query')
            ->andWhere('p.availableAt < :now OR p.availableAt IS NULL')
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%');

        if ($categorySlug) {
            $category = $this->categoryRepository->findOneBySlug($categorySlug);
            if ($category) {
                $qb->join('p.categories', 'c')
                    ->andWhere('c = :category')
                    ->setParameter('category', $category);
            }
        }

        return $qb->orderBy('p.title', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Overrides FindAll() to get sorted
    public function findAll(): array
    {
        return $this->findAllSorted();
    }

    // Finds all products sorted
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p, m')
            ->leftJoin('p.medias', 'm')
            ->andWhere('p.availableAt < :now OR p.availableAt IS NULL')
            ->setParameter('now', new \DateTime())
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds a product by slug with joined data
    public function findOneBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->select('p, m, i, im, if')
            ->leftJoin('p.medias', 'm')
            ->leftJoin('p.items', 'i')
            ->leftJoin('i.media', 'im')
            ->leftJoin('i.file', 'if')
            ->andWhere('p.slug = :slug')
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
    ;
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
