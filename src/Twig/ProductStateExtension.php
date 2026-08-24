<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ProductStateServiceInterface;
use Twig\Attribute\AsTwigFunction;

// A Twig function rather than props threaded through the card, on the model of product_json_ld(): a site overriding the card keeps its badges and its price by calling the same function
class ProductStateExtension
{
    public function __construct(
        private readonly ProductStateServiceInterface $productStateService,
    ) {
    }

    // Returns the badge, price, currency and formats a product's card shows
    #[AsTwigFunction('shop_product_state')]
    public function productState(Product $product): array
    {
        return $this->productStateService->getState($product);
    }

    // Returns the translation key naming the kind of a single item, shown next to its title on the product sheet
    #[AsTwigFunction('shop_item_format')]
    public function itemFormat(ProductItem $item): string
    {
        return $this->productStateService->getItemFormat($item);
    }

    // Returns the file format of a single item, shown as a badge under its picture once the item is open
    #[AsTwigFunction('shop_item_file_format')]
    public function itemFileFormat(ProductItem $item): ?string
    {
        return $this->productStateService->getItemFileFormat($item);
    }

    // Whether a single item ran out rather than being withdrawn from sale, which is the one state a stock alert is offered on
    #[AsTwigFunction('shop_item_sold_out')]
    public function itemSoldOut(ProductItem $item): bool
    {
        return $this->productStateService->isItemSoldOut($item);
    }

    // Returns the price a single item was sold for before, struck through beside its own on the sheet, null unless it is above it
    #[AsTwigFunction('shop_item_price_before')]
    public function itemPriceBefore(ProductItem $item): ?int
    {
        return $this->productStateService->getItemPriceBefore($item);
    }

    // Returns the discount of a single item in whole percents, null when it is not on offer
    #[AsTwigFunction('shop_item_discount')]
    public function itemDiscount(ProductItem $item): ?int
    {
        return $this->productStateService->getItemDiscount($item);
    }
}
