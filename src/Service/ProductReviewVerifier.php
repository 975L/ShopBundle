<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\ReviewVerifierInterface;

// Whether the reviewer actually bought the product, the badge art. L111-7-2 asks for, this bundle being the only one holding both UiBundle's reviews and PaymentBundle's orders
class ProductReviewVerifier implements ReviewVerifierInterface
{
    // The key an order's lines are filed under in Basket::$items, the one this bundle declares (see ProductBasketItemProvider::getKind())
    private const string BASKET_KIND = 'product';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly BasketRepository $basketRepository,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return ShopFavoriteItemProvider::OWNER_TYPE === $ownerType;
    }

    public function hasObtained(string $ownerType, int $ownerId, string $email): bool
    {
        $product = $this->productRepository->find($ownerId);

        if (!$product instanceof Product) {
            return false;
        }

        // Every item the product ever had and not the published ones alone: an item withdrawn from the catalogue since is still what somebody paid for
        $itemIds = array_filter(array_map(static fn (ProductItem $item): ?int => $item->getId(), $product->getItems()->toArray()));

        if ([] === $itemIds) {
            return false;
        }

        // The orders read once and each asked about every item, rather than BasketRepository::hasPaidFor() per item, which would run one query each. Whether an order holds an item is still the basket's own answer (see Basket::holdsItem()), so how the lines are stored stays PaymentBundle's business
        foreach ($this->basketRepository->findPaidByEmail($email) as $basket) {
            foreach ($itemIds as $itemId) {
                if ($basket->holdsItem(self::BASKET_KIND, $itemId)) {
                    return true;
                }
            }
        }

        return false;
    }
}
