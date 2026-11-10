<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Service\OfferSnippetBuilder;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\UiBundle\Service\JsonLdBuilder;
use c975L\UiBundle\Service\RatingSnippetBuilder;

// Builds the schema.org graph a product sheet publishes as JSON-LD, assembled here rather than as microdata, which leaves an empty node behind on an empty field - the one place of the ecosystem emitting an "offers" node, BookBundle leaving it out of its Book graph so the two never publish a price twice
class ProductSnippetBuilder
{
    // The state an item is sold in, as the column stores it and as schema.org names it - Google reads it for its merchant listings, and it is stated per item because the same product can be sold new and second-hand
    public const CONDITIONS = [
        'new' => 'https://schema.org/NewCondition',
        'used' => 'https://schema.org/UsedCondition',
        'refurbished' => 'https://schema.org/RefurbishedCondition',
        'damaged' => 'https://schema.org/DamagedCondition',
    ];

    // What schema.org calls the state of an offer, in the order the shop decides it: nothing left, everything sold, not yet released, on sale
    private const string OUT_OF_STOCK = 'https://schema.org/OutOfStock';
    private const string SOLD_OUT = 'https://schema.org/SoldOut';
    private const string PRE_ORDER = 'https://schema.org/PreOrder';
    private const string IN_STOCK = 'https://schema.org/InStock';

    // Shipping and returns are PaymentBundle's configuration, this bundle only publishing what the sheet displays - the state service being the one the cards and the sheet read their prices from, a struck-through price and the graph's list price can never disagree
    public function __construct(
        private readonly ProductStateServiceInterface $productStateService,
        private readonly RatingSnippetBuilder $ratingSnippetBuilder,
        private readonly OfferSnippetBuilder $offerSnippetBuilder,
        private readonly JsonLdBuilder $jsonLdBuilder = new JsonLdBuilder(),
    ) {
    }

    // $imageUrl and $url are resolved by the caller, only a template turning an attached media into an absolute url; $withRating is the sheet's own condition, so a rich result never shows stars a visitor cannot find on the page
    public function buildProduct(Product $product, ?string $imageUrl = null, ?string $url = null, bool $withRating = false): array
    {
        $name = trim((string) $product->getTitle());

        // No title, no graph: a product node without one indexes nothing
        if ('' === $name) {
            return [];
        }

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'url' => trim((string) $url),
            'description' => $this->jsonLdBuilder->plainText($product->getDescription()),
            'image' => trim((string) $imageUrl),
            // What Google Merchant Center declines a branded offer for not naming, and what stands for a global identifier on an item without a barcode. A product naming no maker carries the shop's own name, PaymentBundle's node as on a gallery print
            'brand' => $this->offerSnippetBuilder->brand($product->getBrand()),
            'category' => $this->category($product),
            // Who the product is meant for, which schema.org says of a Product through an audience and never through the typicalAgeRange a book carries - that property belongs to a CreativeWork, and Google reads this one on a merchant listing
            'audience' => $this->audience($product),
            // What the sheet prints above the description, said where a search engine reads it - the node is UiBundle's, which owns the votes and knows the scale they were cast on
            'aggregateRating' => $withRating ? $this->ratingSnippetBuilder->build('shop_product', (int) $product->getId()) : [],
            // One Offer per purchasable item rather than a single AggregateOffer: the items of one product are free to be priced in different currencies, which no aggregate can express, and each carries its own availability
            'offers' => $this->offers($product, $url),
        ]);
    }

    // The trail leading to a shop page, as the BreadcrumbList a search engine prints in place of the raw url (see UiBundle's JsonLdBuilder)
    /** @param list<array{name: string, url: string}> $trail the levels in reading order, the page's own included */
    public function buildBreadcrumb(array $trail): array
    {
        return $this->jsonLdBuilder->breadcrumb($trail);
    }

    // The products a listing prints, as the ItemList a search engine reads a catalog page through: each element points at the sheet holding the product's own Product node, so a price is published once (see UiBundle's JsonLdBuilder)
    /**
     * @param list<array{name: string, url: string}> $products the cards in the order the page shows them
     * @param int                                    $offset   how many products the pages before this one already listed
     */
    public function buildItemList(array $products, int $offset = 0): array
    {
        return $this->jsonLdBuilder->itemList($products, $offset);
    }

    // The same graph, encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function buildJson(array $snippet): string
    {
        return $this->jsonLdBuilder->encode($snippet);
    }

    // The two ages schema.org reads, off the one range an editor types ("3-8", "15-" from 15 up): a bare minimum is a range of its own, an empty or unreadable one publishes no node at all rather than a node saying nothing
    private function audience(Product $product): array
    {
        if (1 !== preg_match('/^\s*(\d{1,3})\s*-?\s*(\d{1,3})?\s*$/', (string) $product->getAge(), $ages)) {
            return [];
        }

        $min = (int) $ages[1];
        $max = isset($ages[2]) ? (int) $ages[2] : 0;

        // A range written backwards is dropped whole: published as it is, it would describe an audience nobody belongs to
        if (0 !== $max && $max < $min) {
            return [];
        }

        // Not built through clean(), which drops a zero: "0-3" is a range of its own, and a shop selling to the very young says exactly that
        $audience = ['@type' => 'PeopleAudience', 'suggestedMinAge' => $min];

        if (0 !== $max) {
            $audience['suggestedMaxAge'] = $max;
        }

        return $audience;
    }

    // The first category the product belongs to - schema.org takes a single one, and a list of them says nothing more to a search engine
    private function category(Product $product): string
    {
        $category = $product->getCategories()->first();

        return false === $category ? '' : trim((string) $category->getName());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offers(Product $product, ?string $url): array
    {
        $offers = [];

        foreach ($product->getVisibleItems() as $item) {
            $slug = trim((string) $item->getSlug());
            $offers[] = $this->clean([
                '@type' => 'Offer',
                'name' => trim((string) $item->getTitle()),
                // The item's own anchor on the product sheet, which is where a visitor lands to buy it
                'url' => '' === trim((string) $url) ? '' : trim((string) $url) . '#' . $slug,
                // The shop's own reference when it states one, the slug otherwise - which is what every offer of this graph carried before the column existed
                'sku' => '' === trim((string) $item->getSku()) ? $slug : trim((string) $item->getSku()),
                // The barcode number, which is what lets a comparison engine recognize the same product sold by someone else. A product made in-house carries none, and claiming one it does not have is worse than publishing nothing
                'gtin' => trim((string) $item->getGtin()),
                // What the item's card says under its title, carried as the words only, like the product's own description
                'description' => $this->jsonLdBuilder->plainText($item->getDescription()),
                // Prices are stored in cents, schema.org expects the amount as it is charged
                'price' => number_format(((int) $item->getPrice()) / 100, 2, '.', ''),
                'priceCurrency' => strtoupper(trim((string) $item->getCurrency())),
                // What the sheet already prints struck through beside the price, said where a search engine reads it - "ListPrice" is the type Google shows a crossed-out price from
                'priceSpecification' => $this->listPrice($item),
                'availability' => $this->availability($product, $item),
                // Left out when the shop does not state it: a graph claiming "new" over a second-hand item is worse than a graph saying nothing
                'itemCondition' => self::CONDITIONS[(string) $item->getItemCondition()] ?? '',
                'shippingDetails' => $this->shippingDetails($item),
                'hasMerchantReturnPolicy' => $this->offerSnippetBuilder->returnPolicy($this->isDownloaded($item)),
            ]);
        }

        return $offers;
    }

    // The price the item was sold for before the current one, empty unless the state service holds it to be a real one - the sheet and the graph then say the same thing or say nothing, rather than one of them publishing an offer the other does not show
    private function listPrice(ProductItem $item): array
    {
        $before = $this->productStateService->getItemPriceBefore($item);

        if (null === $before) {
            return [];
        }

        return [
            '@type' => 'UnitPriceSpecification',
            'priceType' => 'https://schema.org/ListPrice',
            'price' => number_format($before / 100, 2, '.', ''),
            'priceCurrency' => strtoupper(trim((string) $item->getCurrency())),
        ];
    }

    // What the Shipping component of the sheet already states, said again where a search engine reads it - the node is PaymentBundle's, which owns the grid and the destination. A service is rendered, never posted: the rule is ProductBasketItemProvider's, so a graph and a delivery note never disagree
    private function shippingDetails(ProductItem $item): array
    {
        return true === $item->isService() ? [] : $this->offerSnippetBuilder->shippingDetails($item->getWeight(), $this->isDownloaded($item));
    }

    // A named file is downloaded rather than posted - the empty placeholder ProductItemListener attaches to every new item is not one, here as in ProductBasketItemProvider
    private function isDownloaded(ProductItem $item): bool
    {
        return null !== $item->getFile()?->getName();
    }

    // The very rules AddButton.html.twig disables its button on, plus the product's own release date - a graph saying "in stock" over a button that cannot be clicked is worse than no graph
    private function availability(Product $product, ProductItem $item): string
    {
        if (0 === $item->getLimitedQuantity()) {
            return self::OUT_OF_STOCK;
        }

        if ($item->getLimitedQuantity() > 0 && $item->getOrderedQuantity() >= $item->getLimitedQuantity()) {
            return self::SOLD_OUT;
        }

        $availableAt = $product->getAvailableAt();
        if (null !== $availableAt && $availableAt->format('Ymd') > date('Ymd')) {
            return self::PRE_ORDER;
        }

        return self::IN_STOCK;
    }

    // Drops everything left empty, so an unfilled field never reaches the graph as a blank property
    private function clean(array $snippet): array
    {
        return array_filter($snippet, static fn ($value) => !\in_array($value, ['', [], null, 0], true));
    }
}
