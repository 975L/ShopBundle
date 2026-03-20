<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Repository\ProductAffinityRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;

class ProductRecommendationService implements ProductRecommendationServiceInterface
{
    private const WEIGHT_CATEGORY = 45;
    private const WEIGHT_PRICE = 20;
    private const WEIGHT_CO_PURCHASE = 35;
    private const PRICE_SIMILARITY_TOLERANCE = 0.30;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductAffinityRepository $affinityRepository,
        private readonly ProductItemRepository $productItemRepository,
    ) {
    }

    public function getRecommendationsForBasket(Basket $basket, int $limit = 4): array
    {
        $basketProductIds = $this->getProductIdsFromBasket($basket);
        if (empty($basketProductIds)) {
            return [];
        }

        $referenceProducts = $this->productRepository->findBy(['id' => $basketProductIds]);
        $candidates = $this->getCandidateProducts($basketProductIds);

        // Scores each candidate
        $scoredProducts = [];
        foreach ($candidates as $product) {
            $score = $this->calculateRecommendationScore($product, $referenceProducts);
            if ($score > 0) {
                $scoredProducts[] = [
                    'product' => $product,
                    'score' => $score
                ];
            }
        }

        // Sort by descending score
        usort($scoredProducts, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn($item) => $item['product'],
            array_slice($scoredProducts, 0, $limit)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSimilarProducts(Product $product, int $limit = 4): array
    {
        // Get candidates from the same category
        $categoryIds = array_map(
            fn($cat) => $cat->getId(),
            $product->getCategories()->toArray()
        );

        if (empty($categoryIds)) {
            // No category = take random products
            return $this->productRepository->findRandomProducts($limit + 1, [$product->getId()]);
        }

        // Candidates from the same category
        $candidates = $this->productRepository->findByCategoriesExcluding($categoryIds, [$product->getId()]);

        // Score the candidates
        $scoredProducts = [];
        foreach ($candidates as $candidate) {
            $score = $this->calculateRecommendationScore($candidate, [$product]);

            if ($score > 0) {
                $scoredProducts[] = [
                    'product' => $candidate,
                    'score' => $score
                ];
            }
        }

        // Sort and limit
        usort($scoredProducts, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn($item) => $item['product'],
            array_slice($scoredProducts, 0, $limit)
        );
    }

    public function calculateRecommendationScore(Product $product, array $referenceProducts): float
    {
        $score = 0;

        // CRITERION 1: Same category (40 points max)
        $score += $this->scoreByCategoryMatch($product, $referenceProducts);

        // CRITERION 2: Similar price (20 points max)
        $score += $this->scoreByPriceSimilarity($product, $referenceProducts);

        // CRITERION 3: Historical co-purchases (30 points max)
        $score += $this->scoreByCoPurchase($product, $referenceProducts);

        return round($score, 2);
    }

    private function getProductIdsFromBasket(Basket $basket): array
    {
        $productItemIds = [];
        // Only get 'product' items, NOT 'crowdfunding', 'lottery', etc.
        $items = $basket->getItems()['product'] ?? [];
        foreach ($items as $id => $item) {
            $productItemIds[] = $id;
        }

        if (empty($productItemIds)) {
            return [];
        }

        // Get ProductItem entities and extract unique Product IDs
        $productItems = $this->productItemRepository->findBy(['id' => $productItemIds]);
        $productIds = [];

        foreach ($productItems as $productItem) {
            $product = $productItem->getProduct();
            // Ensure ProductItem has a valid Product (not null, not Crowdfunding)
            if ($product !== null) {
                $productIds[] = $product->getId();
            }
        }

        return array_unique($productIds);
    }

    // For now, get all available products excluding those in the basket
    private function getCandidateProducts(array $excludeProductIds): array
    {
        return $this->productRepository->findAvailableProductsExcluding($excludeProductIds);
    }

    private function scoreByCategoryMatch(Product $product, array $referenceProducts): float
    {
        if (empty($referenceProducts)) {
            return 0;
        }

        $productCategoryIds = array_map(
            fn($cat) => $cat->getId(),
            $product->getCategories()->toArray()
        );

        if (empty($productCategoryIds)) {
            return 0;
        }

        // Count how many reference products share at least one category
        $matchCount = 0;
        foreach ($referenceProducts as $refProduct) {
            $refCategoryIds = array_map(
                fn($cat) => $cat->getId(),
                $refProduct->getCategories()->toArray()
            );

            // Is there an intersection?
            if (!empty(array_intersect($productCategoryIds, $refCategoryIds))) {
                $matchCount++;
            }
        }

        // Ratio: how many reference products match?
        $ratio = $matchCount / count($referenceProducts);

        return self::WEIGHT_CATEGORY * $ratio;
    }

    private function scoreByPriceSimilarity(Product $product, array $referenceProducts): float
    {
        if (empty($referenceProducts)) {
            return 0;
        }

        // Calculate the average price of product items
        $productPrice = $this->getAverageProductPrice($product);

        if ($productPrice === 0) {
            return 0;
        }

        // Calculate the average price of reference products
        $referencePrices = array_map(
            fn($p) => $this->getAverageProductPrice($p),
            $referenceProducts
        );

        $referencePrices = array_filter($referencePrices); // Remove zeros

        if (empty($referencePrices)) {
            return 0;
        }

        $avgReferencePrice = array_sum($referencePrices) / count($referencePrices);

        // Calculate the percentage difference
        $diff = abs($productPrice - $avgReferencePrice) / $avgReferencePrice;

        // If difference is < tolerance, max score, otherwise degressive
        if ($diff <= self::PRICE_SIMILARITY_TOLERANCE) {
            return self::WEIGHT_PRICE;
        } elseif ($diff <= self::PRICE_SIMILARITY_TOLERANCE * 2) {
            // Half score if difference between 30% and 60%
            return self::WEIGHT_PRICE / 2;
        }

        return 0;
    }

    private function scoreByCoPurchase(Product $product, array $referenceProducts): float
    {
        if (empty($referenceProducts)) {
            return 0;
        }

        $totalAffinityScore = 0;
        $maxPossibleScore = 0;

        foreach ($referenceProducts as $refProduct) {
            $affinityScore = $this->affinityRepository->getAffinityScore(
                $refProduct->getId(),
                $product->getId()
            );

            if ($affinityScore !== null) {
                // Affinity score is between 0 and 100
                $totalAffinityScore += $affinityScore;
            }

            $maxPossibleScore += 100; // Max affinity score
        }

        if ($maxPossibleScore === 0) {
            return 0;
        }

        // Normalize: ratio of total score over max possible
        $ratio = $totalAffinityScore / $maxPossibleScore;

        return self::WEIGHT_CO_PURCHASE * $ratio;
    }

    private function getAverageProductPrice(Product $product): float
    {
        $items = $product->getItems();

        if ($items->isEmpty()) {
            return 0;
        }

        $prices = [];
        foreach ($items as $item) {
            $prices[] = $item->getPrice();
        }

        return array_sum($prices) / count($prices);
    }
}
