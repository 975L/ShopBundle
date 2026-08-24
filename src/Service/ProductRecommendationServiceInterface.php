<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Product;

interface ProductRecommendationServiceInterface
{
    /**
     * Cross-sells against everything already in the basket, best score first.
     *
     * @return Product[] at most $limit, empty when the basket holds no product to recommend from
     */
    public function getRecommendationsForBasket(Basket $basket, int $limit = 4): array;

    /**
     * Same scoring against a single product, for its own detail page.
     *
     * @return Product[] at most $limit, best score first
     */
    public function getSimilarProducts(Product $product, int $limit = 4): array;

    /**
     * How closely $product matches the reference set - only a score above 0 is ever recommended.
     *
     * @param Product[] $referenceProducts the products to score against
     */
    public function calculateRecommendationScore(Product $product, array $referenceProducts): float;
}
