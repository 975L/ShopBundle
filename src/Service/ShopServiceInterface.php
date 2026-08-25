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

interface ShopServiceInterface
{
    /**
     * The sorted products, 12 per page - the shop's own front page, wider than ProductServiceInterface::findAllPaginated()'s 9.
     *
     * @param InputBag $query the request's query bag, its "p" parameter holding the 1-based page number
     *
     * @return Pagination<Product>
     */
    public function findAllProductsPaginated($query);

    /**
     * The order the listing was asked for, among "newest", "price_asc" and "price_desc".
     *
     * @param InputBag $query the request's query bag, its "order" parameter holding the order
     *
     * @return string|null null when nothing valid was asked, the shop's own positions then applying
     */
    public function getOrder($query): ?string;

    /**
     * The filters the listing was asked for, each null when nothing usable was asked.
     *
     * @param InputBag $query the request's query bag, its "price", "format" and "stock" parameters holding the filters
     *
     * @return array{price: ?string, format: ?string, stock: ?string} the price as a "min-max" range in cents, its upper bound left empty on the open-ended band
     */
    public function getFilters($query): array;

    /**
     * The price bands the filter offers, cut from the catalogue's own dearest item.
     *
     * @return list<array{value: string, min: int, max: ?int}> empty when the shop has nothing priced, the amounts being in cents
     */
    public function getPriceBrackets(): array;

    /**
     * The number of categories the shop publishes, shown next to the number of products.
     */
    public function countCategories(): int;
}
