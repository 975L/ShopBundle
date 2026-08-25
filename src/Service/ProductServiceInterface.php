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
use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;

interface ProductServiceInterface
{
    /**
     * @return Product[] in no particular order
     */
    public function findAll();

    /**
     * @return Product[] ordered by their admin-defined position
     */
    public function findAllSorted();

    /**
     * The sorted products, 9 per page.
     *
     * @param InputBag $query the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return Pagination<Product>
     */
    public function findAllPaginated($query);

    /**
     * @return Product|null null when no product carries that id
     */
    public function findOneById(int $id): ?Product;

    /**
     * @param string $slug the slug of the category
     *
     * @return Product[] the published products of that category, ordered by their admin-defined position
     */
    public function findByCategorySlug(string $slug);

    /**
     * @param string      $query        the free-text search terms
     * @param string|null $categorySlug restricts the search to one category, null searching them all
     *
     * @return Product[]
     */
    public function search(string $query, ?string $categorySlug = null);

    // Persists and flushes the product.
    public function save(Product $product): void;
}
