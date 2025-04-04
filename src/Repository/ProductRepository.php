<?php

namespace c975L\ShopBundle\Repository;

use c975L\ShopBundle\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    // Finds products based on search
    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.title', 'DESC')
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
            ->orderBy('p.position', 'ASC')
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
