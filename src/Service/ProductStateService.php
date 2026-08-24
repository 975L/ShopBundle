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

// Everything a product card shows besides its own columns - price, formats, availability - is read from its items by the listing, the sheet and the "shop_product" block alike, so it is resolved once here, and only over the published items, one taken offline setting neither price nor format
class ProductStateService implements ProductStateServiceInterface
{
    // How long a product wears the "new" badge, counted from its creation date
    private const int NEW_DAYS = 30;

    // Returns the card's badge, price and formats, the badge being the single most useful thing to say: what cannot be bought comes before what is running out, which comes before what is merely recent
    public function getState(Product $product): array
    {
        $items = $product->getPublishedItems();
        $cheapest = $this->getCheapestItem($product);
        $price = null === $cheapest ? null : (int) $cheapest->getPrice();
        $soldOut = $this->isSoldOut($product);
        // Told apart for the badge alone: both are unbuyable, but a shortage the shop expects to end is not an item withdrawn for good
        $outOfStock = $soldOut && $this->hasItemSoldOut($product);
        $prices = [];

        foreach ($items as $item) {
            $prices[] = (int) $item->getPrice();
        }

        // The struck-through price is read from the very item the card prints the price of, never from another one: the two figures shown side by side have to name the same offer
        $discount = null === $cheapest ? null : $this->getItemDiscount($cheapest);

        return [
            'badge' => $this->getBadge($product, $soldOut, $outOfStock, $price, $discount),
            'soldOut' => $soldOut,
            'availableAt' => $this->getAvailableAt($product),
            'price' => $price,
            'priceBefore' => null === $cheapest ? null : $this->getItemPriceBefore($cheapest),
            'discount' => $discount,
            'currency' => $this->getCurrency($product),
            'singlePrice' => 1 === count(array_unique($prices)),
            'formats' => $this->getFormats($product),
        ];
    }

    // The price an item was sold for before the current one, null unless it is genuinely above it - a value left behind by an offer that ended, or typed below the price, publishes nothing rather than a discount of zero or less
    public function getItemPriceBefore(ProductItem $item): ?int
    {
        $before = $item->getPriceBefore();

        return null !== $before && $before > (int) $item->getPrice() ? $before : null;
    }

    // The discount of an item as whole percents, null when it is not on offer or when the cut rounds down to nothing - a "-0 %" badge is worse than no badge
    public function getItemDiscount(ProductItem $item): ?int
    {
        $before = $this->getItemPriceBefore($item);
        if (null === $before) {
            return null;
        }

        $discount = (int) round((($before - (int) $item->getPrice()) / $before) * 100);

        return $discount >= 1 ? $discount : null;
    }

    // A null limited quantity is an unlimited stock, 0 an item withdrawn from sale, and anything above what has already been ordered is still buyable
    public function isItemAvailable(ProductItem $item): bool
    {
        $limited = $item->getLimitedQuantity();

        return null === $limited || $limited > (int) $item->getOrderedQuantity();
    }

    // Out of stock is not the same as withdrawn: an item capped at 0 was taken off sale and is not expected back, so nothing is promised on it
    public function isItemSoldOut(ProductItem $item): bool
    {
        return (int) $item->getLimitedQuantity() > 0 && !$this->isItemAvailable($item);
    }

    // Returns the lowest price of the items, in cents
    public function getLowestPrice(Product $product): ?int
    {
        $prices = [];

        foreach ($product->getPublishedItems() as $item) {
            $prices[] = (int) $item->getPrice();
        }

        return [] === $prices ? null : min($prices);
    }

    // The kind of a single item, read the same way as PaymentBundle's own Item:Type component
    public function getItemFormat(ProductItem $item): string
    {
        if (true === $item->isService()) {
            return 'label.service';
        }

        return null !== $item->getFile() && null !== $item->getFile()->getName() ? 'label.digital' : 'label.physical';
    }

    // Returns the file format of an item written out, "PDF" or "MP3", null when it carries no file
    public function getItemFileFormat(ProductItem $item): ?string
    {
        $file = $item->getFile();
        $name = null !== $file ? $file->getName() : null;
        if (null === $name) {
            return null;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return '' !== $extension ? strtoupper($extension) : null;
    }

    // Returns the cheapest item of the product, which is the one its card speaks for, null when it carries none
    private function getCheapestItem(Product $product): ?ProductItem
    {
        $cheapest = null;

        foreach ($product->getPublishedItems() as $item) {
            if (null === $cheapest || (int) $item->getPrice() < (int) $cheapest->getPrice()) {
                $cheapest = $item;
            }
        }

        return $cheapest;
    }

    // Returns the translation key of the badge, null when the product has nothing particular to say
    private function getBadge(Product $product, bool $soldOut, bool $outOfStock, ?int $price, ?int $discount = null): ?string
    {
        if (null !== $this->getAvailableAt($product)) {
            return 'label.coming_soon';
        }

        // What is expected back says so, where what was withdrawn is simply over
        if ($outOfStock) {
            return 'label.out_of_stock';
        }

        if ($soldOut) {
            return 'label.sold_out';
        }

        // A price cut outranks the scarcity below it, being the one thing the visitor can act on - except on something given away, where "free" is the truthful word and "-100 %" the misleading one
        if (null !== $discount && 0 !== $price) {
            return 'label.discount';
        }

        if ($this->hasLimitedQuantity($product)) {
            return 'label.limited_quantity';
        }

        if (0 === $price) {
            return 'label.free';
        }

        return $this->isNew($product) ? 'label.new' : null;
    }

    // Returns the availability date when it is still ahead, null when the product is already on sale
    private function getAvailableAt(Product $product): ?\DateTimeInterface
    {
        $availableAt = $product->getAvailableAt();

        return null !== $availableAt && $availableAt > new \DateTime() ? $availableAt : null;
    }

    // Whether at least one item is out of stock rather than withdrawn - what tells the two unbuyable states apart on the card
    private function hasItemSoldOut(Product $product): bool
    {
        foreach ($product->getPublishedItems() as $item) {
            if ($this->isItemSoldOut($item)) {
                return true;
            }
        }

        return false;
    }

    // A product cannot be bought when not one of its items can still be ordered - a product with nothing published at all is not sold out, it is simply not for sale yet
    private function isSoldOut(Product $product): bool
    {
        $items = $product->getPublishedItems();
        if (0 === count($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($this->isItemAvailable($item)) {
                return false;
            }
        }

        return true;
    }

    // Whether at least one item is capped, whatever is left of it
    private function hasLimitedQuantity(Product $product): bool
    {
        foreach ($product->getPublishedItems() as $item) {
            if ((int) $item->getLimitedQuantity() > 0) {
                return true;
            }
        }

        return false;
    }

    // Whether the product was created within the last NEW_DAYS days
    private function isNew(Product $product): bool
    {
        $creation = $product->getCreation();

        return null !== $creation && $creation > new \DateTime('-' . self::NEW_DAYS . ' days');
    }

    // The currency of the items, the euro when the product carries none
    private function getCurrency(Product $product): string
    {
        foreach ($product->getPublishedItems() as $item) {
            if (null !== $item->getCurrency()) {
                return $item->getCurrency();
            }
        }

        return 'eur';
    }

    // The distinct kinds of items the product is sold as, in the order a visitor reads them - a physical copy first, then a file, then a service
    private function getFormats(Product $product): array
    {
        $formats = [];

        foreach ($product->getPublishedItems() as $item) {
            $formats[] = $this->getItemFormat($item);
        }

        return array_values(array_intersect(['label.physical', 'label.digital', 'label.service'], $formats));
    }
}
