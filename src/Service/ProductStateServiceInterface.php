<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;

interface ProductStateServiceInterface
{
    /**
     * What a product's card says of itself, read from its items rather than stored on it.
     *
     * @return array{badge: ?string, soldOut: bool, availableAt: ?\DateTimeInterface, price: ?int, priceBefore: ?int, discount: ?int, currency: string, singlePrice: bool, formats: string[]}
     *                                                                                                                                                                                        the badge and the formats are translation keys of the "shop" domain, the prices are in cents, the discount is in whole percents
     */
    public function getState(Product $product): array;

    /**
     * The lowest price of a product's items, in cents, null when it has none.
     */
    public function getLowestPrice(Product $product): ?int;

    /**
     * The price a single item was sold for before the current one, in cents, null unless it is above it.
     */
    public function getItemPriceBefore(ProductItem $item): ?int;

    /**
     * The discount of a single item in whole percents, null when it is not on offer.
     */
    public function getItemDiscount(ProductItem $item): ?int;

    /**
     * Whether a single item can still be ordered: a null limited quantity is an unlimited stock, 0 an item
     * withdrawn from sale, and anything above what has already been ordered is still buyable.
     *
     * Public because three readers need the same answer - the card's badge, the add button, and the stock alerts
     * that only go out on an item that has genuinely come back.
     */
    public function isItemAvailable(ProductItem $item): bool;

    /**
     * Whether a single item is out of stock rather than withdrawn: it is capped, and the cap has been reached.
     * The one state a "tell me when it is back" subscription is offered on, an item set to 0 not being expected back.
     */
    public function isItemSoldOut(ProductItem $item): bool;

    /**
     * The kind a single item is sold as, as a translation key of the "shop" domain.
     */
    public function getItemFormat(ProductItem $item): string;

    /**
     * The file format a single item is downloaded as, written out, null when it carries no file.
     */
    public function getItemFileFormat(ProductItem $item): ?string;
}
