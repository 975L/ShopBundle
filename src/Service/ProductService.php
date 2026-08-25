<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Service\Paginator;
use Doctrine\ORM\EntityManagerInterface;

class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly Paginator $paginator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Finds all
    public function findAll()
    {
        return $this->productRepository->findAll();
    }

    // Finds all sorted by position
    public function findAllSorted()
    {
        return $this->productRepository->findAllSorted();
    }

    // Gets the products paginated
    public function findAllPaginated($query)
    {
        return $this->paginator->paginate(
            $this->findAllSorted(),
            $this->paginator->getPage($query),
            9
        );
    }

    // Finds one by id
    public function findOneById(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    // Finds the available products of a category, which is what its page lists
    public function findByCategorySlug(string $slug)
    {
        return $this->productRepository->findByCategorySlug($slug);
    }

    // Searches for product
    public function search(string $query, ?string $categorySlug = null)
    {
        return $this->productRepository->search($query, $categorySlug);
    }

    // Saves the product
    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }
}
