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
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;

// The product feed Google Merchant Center fetches on schedule, in its RSS 2.0 dialect: built from the very graph ProductSnippetBuilder publishes on each sheet rather than read again from the entities, so the feed and the page can never state two prices. One feed per language the products are written in, a feed declaring a single "content language" - no translation is laid over the texts, which stay the ones the product was written with (see Product::$language)
class ProductMerchantFeed
{
    public const string NAMESPACE = 'http://base.google.com/ns/1.0';

    // What a downloaded file can be and still be listed: Google refuses digital books (PDF, EPUB) on Shopping, audiobooks excepted, so a downloaded item is in the feed only when it is one of these
    private const array AUDIO_EXTENSIONS = ['aac', 'flac', 'm4a', 'm4b', 'mp3', 'oga', 'ogg', 'opus', 'wav'];

    // schema.org's terms as the feed spells them - a damaged item being sold as used, the feed having no word of its own for it
    private const array AVAILABILITIES = ['InStock' => 'in_stock', 'OutOfStock' => 'out_of_stock', 'SoldOut' => 'out_of_stock', 'PreOrder' => 'preorder'];
    private const array CONDITIONS = ['NewCondition' => 'new', 'UsedCondition' => 'used', 'RefurbishedCondition' => 'refurbished', 'DamagedCondition' => 'used'];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ProductServiceInterface $productService,
        private readonly ProductSnippetBuilder $snippetBuilder,
        private readonly ProductImageUrlResolver $imageUrlResolver,
        private readonly ShopPublicUrlResolver $shopPublicUrlResolver,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // The feed of the products written in $language, the site's writing language when none is given - null while "site-url" is not configured, Merchant Center accepting no relative link
    public function build(?string $language = null): ?string
    {
        // A shop in test mode is kept out of the engines (see ShopSitemapProvider), and a feed would send Merchant Center to its noindex pages
        if ((bool) $this->configService->get('shop-test-mode')) {
            return null;
        }

        $language ??= $this->siteLocales->getDefaultLocale();

        $shopUrl = $this->shopPublicUrlResolver->resolve('shop_index');
        if (null === $shopUrl) {
            return null;
        }

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', self::NAMESPACE);
        $xml->startElement('channel');
        $xml->writeElement('title', (string) $this->configService->get('site-name'));
        $xml->writeElement('link', $shopUrl);
        $xml->writeElement('description', (string) $this->configService->get('site-name'));

        foreach ($this->productService->findAll() as $product) {
            if (($product->getLanguage() ?? $this->siteLocales->getDefaultLocale()) !== $language) {
                continue;
            }

            foreach ($this->items($product) as $fields) {
                $this->writeItem($xml, $fields);
            }
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    // The url of each feed Merchant Center is given, keyed by language: the site's writing language without parameter, then every other one a product is written in - empty while "site-url" is not configured
    /** @return array<string, string> */
    public function urls(): array
    {
        $default = $this->siteLocales->getDefaultLocale();
        $languages = [$default];
        foreach ($this->productService->findAll() as $product) {
            $languages[] = $product->getLanguage() ?? $default;
        }

        $urls = [];
        foreach (array_unique($languages) as $language) {
            $url = $this->shopPublicUrlResolver->resolve('shop_merchant_feed', $default === $language ? [] : ['language' => $language]);
            if (null === $url) {
                return [];
            }

            $urls[$language] = $url;
        }

        return $urls;
    }

    // One entry per item Google accepts, its fields in the feed's own names
    /** @return list<array<string, string|array<string, string>>> */
    public function items(Product $product): array
    {
        // The canonical url, without the item's anchor the graph's offers carry: it is the one the sheet declares, which is what Merchant Center matches the page against
        $url = $this->shopPublicUrlResolver->resolve('product_display', ['slug' => $product->getSlug()]);
        if (null === $url) {
            return [];
        }

        $productImage = $this->imageUrlResolver->forProduct($product);
        $graph = $this->snippetBuilder->buildProduct($product, $productImage, $url, $this->imageUrlResolver->forItems($product));

        // Each offer paired with the item it was built from by the sku the graph gives it - the item's own, its slug otherwise (see ProductSnippetBuilder::offers())
        $productItems = [];
        foreach ($product->getVisibleItems() as $productItem) {
            $sku = trim((string) $productItem->getSku());
            $productItems['' === $sku ? trim((string) $productItem->getSlug()) : $sku] = $productItem;
        }

        $items = [];
        foreach ($graph['offers'] ?? [] as $offer) {
            $item = $this->listedItem($productItems, $offer);
            if (null !== $item) {
                $items[] = $this->fields($graph, $offer, $product, $item, $url, (string) $productImage);
            }
        }

        return $items;
    }

    // The item an offer was built from, null when Merchant Center refuses it: a free item, a price being what it lists, a price stated in no currency, or a file Google does not list
    /** @param array<string, ProductItem> $productItems */
    private function listedItem(array $productItems, array $offer): ?ProductItem
    {
        $item = $productItems[$offer['sku'] ?? ''] ?? null;
        if (null === $item || !$this->isListable($item) || 0.0 === (float) $offer['price'] || '' === ($offer['priceCurrency'] ?? '')) {
            return null;
        }

        return $item;
    }

    // The fields of one entry, those the graph leaves out left out here too
    /** @return array<string, string|array<string, string>> */
    private function fields(array $graph, array $offer, Product $product, ProductItem $item, string $url, string $productImage): array
    {
        return array_filter(
            [...$this->identityFields($graph, $offer, $product, $item, $url, $productImage), ...$this->offerFields($offer)],
            static fn (string | array $value): bool => '' !== $value && [] !== $value
        );
    }

    // What the entry is: its ids, its texts, its links and what identifies it
    /** @return array<string, string> */
    private function identityFields(array $graph, array $offer, Product $product, ProductItem $item, string $url, string $productImage): array
    {
        // The keys the graph leaves out when they are empty, read as such
        $offer += ['name' => '', 'description' => $graph['description'] ?? $graph['name'], 'image' => $productImage, 'gtin' => ''];
        $gtin = (string) $offer['gtin'];
        $brand = (string) ($graph['brand']['name'] ?? '');

        return [
            // Ids rather than slugs, which run past the 50 characters the feed allows
            'g:id' => 'shop-item-' . $item->getId(),
            'g:item_group_id' => 'shop-product-' . $product->getId(),
            'g:title' => mb_substr($graph['name'] . ' - ' . $offer['name'], 0, 150),
            'g:description' => mb_substr((string) $offer['description'], 0, 5000),
            'g:link' => $url,
            'g:image_link' => (string) $offer['image'],
            'g:gtin' => $gtin,
            'g:brand' => $brand,
            // What Merchant Center asks of a product that has neither, rather than flagging it as missing them
            'g:identifier_exists' => '' === $gtin && '' === $brand ? 'no' : '',
            'g:product_type' => (string) ($graph['category'] ?? ''),
        ];
    }

    // What the entry is sold for: its availability, its prices, its condition and its shipping
    /** @return array<string, string|array<string, string>> */
    private function offerFields(array $offer): array
    {
        // The keys the graph leaves out when they are empty, read as such
        $offer += ['availability' => '', 'itemCondition' => '', 'priceSpecification' => null, 'shippingDetails' => null];
        $price = $offer['price'] . ' ' . $offer['priceCurrency'];
        $listPrice = $offer['priceSpecification'];
        $shipping = $offer['shippingDetails'];

        return [
            'g:availability' => self::AVAILABILITIES[basename((string) $offer['availability'])] ?? 'in_stock',
            // A struck-through price is the regular one, the price charged being the sale one
            'g:price' => null === $listPrice ? $price : $listPrice['price'] . ' ' . $listPrice['priceCurrency'],
            'g:sale_price' => null === $listPrice ? '' : $price,
            'g:condition' => self::CONDITIONS[basename((string) $offer['itemCondition'])] ?? '',
            'g:shipping' => null === $shipping ? [] : [
                'g:country' => $shipping['shippingDestination']['addressCountry'],
                'g:price' => $shipping['shippingRate']['value'] . ' ' . $shipping['shippingRate']['currency'],
            ],
        ];
    }

    // A posted item, or a downloaded one Google lists (see AUDIO_EXTENSIONS)
    private function isListable(ProductItem $item): bool
    {
        $name = $item->getFile()?->getName();

        return null === $name || \in_array(strtolower(pathinfo($name, \PATHINFO_EXTENSION)), self::AUDIO_EXTENSIONS, true);
    }

    // One <item>, a nested field written as its own element holding its sub-fields
    /** @param array<string, string|array<string, string>> $fields */
    private function writeItem(\XMLWriter $xml, array $fields): void
    {
        $xml->startElement('item');
        foreach ($fields as $name => $value) {
            if (\is_array($value)) {
                $xml->startElement($name);
                foreach ($value as $subName => $subValue) {
                    $xml->writeElement($subName, $subValue);
                }
                $xml->endElement();

                continue;
            }

            $xml->writeElement($name, $value);
        }
        $xml->endElement();
    }
}
