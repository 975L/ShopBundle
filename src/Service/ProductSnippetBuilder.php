<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
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
        private readonly ConfigServiceInterface $configService,
        private readonly ProductStateServiceInterface $productStateService,
        private readonly RatingSnippetBuilder $ratingSnippetBuilder,
    ) {
    }

    // $imageUrl, $url and $itemImageUrls are resolved by the caller, only a template turning an attached media into an absolute url; $withRating is the sheet's own condition, so a rich result never shows stars a visitor cannot find on the page
    public function buildProduct(Product $product, ?string $imageUrl = null, ?string $url = null, array $itemImageUrls = [], bool $withRating = false): array
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
            'description' => $this->plainText($product->getDescription()),
            'image' => trim((string) $imageUrl),
            // The slug rather than an identifier of its own: a reference is stated item by item, the same product being sold in several of them, and the slug is what names the product everywhere else
            'sku' => trim((string) $product->getSlug()),
            // What Google Merchant Center declines a branded offer for not naming. Left out of the graph when the shop makes the product itself, which carries no brand of its own
            'brand' => $this->brand($product),
            'category' => $this->category($product),
            // What the sheet prints above the description, said where a search engine reads it - the node is UiBundle's, which owns the votes and knows the scale they were cast on
            'aggregateRating' => $withRating ? $this->ratingSnippetBuilder->build('shop_product', (int) $product->getId()) : [],
            // One Offer per purchasable item rather than a single AggregateOffer: the items of one product are free to be priced in different currencies, which no aggregate can express, and each carries its own availability
            'offers' => $this->offers($product, $url, $itemImageUrls),
        ]);
    }

    /**
     * The trail leading to the page, as the BreadcrumbList a search engine prints in place of the raw url.
     *
     * The levels are handed over already resolved, for the same reason the product's own urls are: only the caller can turn a route into an address.
     *
     * @param list<array{name: string, url: string}> $trail the levels in reading order, the page's own included
     */
    public function buildBreadcrumb(array $trail): array
    {
        $elements = [];
        $position = 0;

        foreach ($trail as $level) {
            $name = trim($level['name']);
            $url = trim($level['url']);

            // A level with nothing to show is dropped rather than numbered: a list whose positions skip one is a malformed breadcrumb
            if ('' === $name || '' === $url) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                'item' => $url,
            ];
        }

        // One level is the page itself, and a trail leading nowhere tells a search engine nothing it does not already read in the url
        if (count($elements) < 2) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * The products a listing prints, as the ItemList a search engine reads a catalog page through.
     *
     * The summary form and not the whole graph: each element points at the sheet where the product's own Product node lives, so a price is published once, on the page that sells it, and a listing of thirty cards stays a handful of lines rather than thirty nested graphs.
     *
     * The levels are handed over already resolved, like the breadcrumb's own: only the caller can turn a route into an address.
     *
     * @param list<array{name: string, url: string}> $products the cards in the order the page shows them
     * @param int                                    $offset   how many products the pages before this one already listed, so the second page numbers its cards from where the first stopped rather than from one again
     */
    public function buildItemList(array $products, int $offset = 0): array
    {
        $elements = [];
        $position = max(0, $offset);

        foreach ($products as $product) {
            $name = trim($product['name']);
            $url = trim($product['url']);

            // A card with nothing to point at is dropped rather than numbered, for the same reason as a breadcrumb level: a list whose positions skip one is malformed
            if ('' === $name || '' === $url) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                'url' => $url,
            ];
        }

        if ([] === $elements) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            // What this page holds and not what the whole shop does: the next page publishes its own list, and a count claiming more than the elements below it is what a validator refuses
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }

    // The same graph, encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function buildJson(array $snippet): string
    {
        if ([] === $snippet) {
            return '';
        }

        // JSON_HEX_TAG keeps a "</script>" typed into a field from closing the tag, JSON_INVALID_UTF8_SUBSTITUTE keeps a stray byte from emptying the whole graph
        return json_encode($snippet, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // The maker of the product as a Brand node, empty when it names none
    private function brand(Product $product): array
    {
        $brand = trim((string) $product->getBrand());

        return '' === $brand ? [] : ['@type' => 'Brand', 'name' => $brand];
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
    private function offers(Product $product, ?string $url, array $itemImageUrls = []): array
    {
        $offers = [];

        foreach ($product->getPublishedItems() as $item) {
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
                'description' => $this->plainText($item->getDescription()),
                // The item's own picture, which is not the product's: a format, a colour or an edition is shown by its own image on the sheet
                'image' => trim((string) ($itemImageUrls[$slug] ?? '')),
                // Prices are stored in cents, schema.org expects the amount as it is charged
                'price' => number_format(((int) $item->getPrice()) / 100, 2, '.', ''),
                'priceCurrency' => strtoupper(trim((string) $item->getCurrency())),
                // What the sheet already prints struck through beside the price, said where a search engine reads it - "ListPrice" is the type Google shows a crossed-out price from
                'priceSpecification' => $this->listPrice($item),
                'availability' => $this->availability($product, $item),
                // Left out when the shop does not state it: a graph claiming "new" over a second-hand item is worse than a graph saying nothing
                'itemCondition' => self::CONDITIONS[(string) $item->getItemCondition()] ?? '',
                'shippingDetails' => $this->shippingDetails($item),
                'hasMerchantReturnPolicy' => $this->returnPolicy(),
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

    // What the Shipping component of the sheet already states, said again where a search engine reads it. No destination and no delivery time: neither is configured anywhere, and a guessed country in a rich result is worse than a missing one
    private function shippingDetails(ProductItem $item): array
    {
        $shipping = (int) $this->configService->get('shop-shipping');

        // A shop that charges nothing for shipping has nothing to declare here rather than a zero rate, which reads as free shipping. The rule telling a shipped item from the rest is ProductBasketItemProvider's, so a graph and a delivery note never disagree: a file is downloaded, a service is rendered, everything else is posted
        if ($shipping <= 0 || null !== $item->getFile()?->getName() || true === $item->isService()) {
            return [];
        }

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => number_format($shipping / 100, 2, '.', ''),
                'currency' => strtoupper(trim((string) $this->configService->get('shop-currency'))),
            ],
        ];
    }

    // The link and nothing else: the return window and the country are written in the terms of sales, which no column of this ecosystem parses - publishing a window nobody configured would be a promise the shop never made
    private function returnPolicy(): array
    {
        $url = trim((string) $this->configService->get('url-terms-of-sales'));

        if ('' === $url) {
            return [];
        }

        return [
            '@type' => 'MerchantReturnPolicy',
            'merchantReturnLink' => $url,
        ];
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

    // The description is rich text; a graph carries the words only
    private function plainText(mixed $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // Drops everything left empty, so an unfilled field never reaches the graph as a blank property
    private function clean(array $snippet): array
    {
        return array_filter($snippet, static fn ($value) => !\in_array($value, ['', [], null, 0], true));
    }
}
