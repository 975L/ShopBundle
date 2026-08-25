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
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Service\Paginator;

class ShopService implements ShopServiceInterface
{
    // The orders the listing offers, anything else falling back on the shop's own positions
    private const array ORDERS = ['newest', 'price_asc', 'price_desc'];

    // The kinds the format filter narrows on - the very three ProductStateService resolves, minus the "label." its own keys carry
    private const array FORMATS = ['physical', 'digital', 'service'];

    // How many bands the price filter offers. They are cut from the catalogue's own prices rather than from figures written here, which would suit one shop and no other - from the highest "from" price, the very one matchesPrice() compares a band against
    private const int PRICE_BRACKETS = 4;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly ProductStateServiceInterface $productStateService,
        private readonly Paginator $paginator,
    ) {
    }

    // Gets the products paginated
    public function findAllProductsPaginated($query)
    {
        $order = $this->getOrder($query);
        $products = $this->productRepository->findAllSorted($order);
        $products = $this->filter($products, $this->getFilters($query));

        // A product's price is the lowest of its items, which no ORDER BY can read without collapsing the rows the joined medias and items spread it over - a page of products is short enough to order here
        if ('price_asc' === $order || 'price_desc' === $order) {
            $products = $this->sortByPrice($products, 'price_desc' === $order);
        }

        return $this->paginator->paginate(
            $products,
            $this->paginator->getPage($query),
            12
        );
    }

    // Gets the requested order, null when the query asks for one the listing does not offer
    public function getOrder($query): ?string
    {
        $order = (string) $query->get('order', '');

        return in_array($order, self::ORDERS, true) ? $order : null;
    }

    // Gets the filters the listing was asked for, each null when nothing usable was asked
    public function getFilters($query): array
    {
        $price = (string) $query->get('price', '');
        $format = (string) $query->get('format', '');

        return [
            // Read as a range rather than checked against the bands currently offered: those are cut from the catalogue's own prices, so a shared url has to keep working once a dearer product has moved them
            'price' => 1 === preg_match('/^\d+-\d*$/', $price) ? $price : null,
            'format' => in_array($format, self::FORMATS, true) ? $format : null,
            'stock' => 'available' === (string) $query->get('stock', '') ? 'available' : null,
        ];
    }

    // The price bands the filter offers, empty when the shop has nothing priced to cut them from
    public function getPriceBrackets(): array
    {
        $max = $this->productRepository->findMaxLowestItemPrice();
        if ($max <= 0) {
            return [];
        }

        // Rounded up to whole euros, so the bands read as prices rather than as the catalogue's own extremes
        $step = (int) ceil($max / self::PRICE_BRACKETS / 100) * 100;
        $brackets = [];

        for ($i = 0; $i < self::PRICE_BRACKETS; ++$i) {
            $min = $i * $step;
            $last = self::PRICE_BRACKETS - 1 === $i;

            // The last band is left open-ended: a catalogue gaining a dearer product would otherwise have it cut out by the shop's own filter
            $brackets[] = [
                'value' => $min . '-' . ($last ? '' : (string) ($min + $step)),
                'min' => $min,
                'max' => $last ? null : $min + $step,
            ];
        }

        return $brackets;
    }

    // Counts the categories the shop publishes
    public function countCategories(): int
    {
        return count($this->productCategoryRepository->findAll());
    }

    // Narrows the listing on what the filter row asks. All three read the items, which no WHERE can reach without collapsing the rows the joined medias and items spread a product over, so they are applied here as the price ordering already is
    private function filter(array $products, array $filters): array
    {
        if (null === $filters['price'] && null === $filters['format'] && null === $filters['stock']) {
            return $products;
        }

        return array_values(array_filter($products, function ($product) use ($filters): bool {
            // Resolved once per product and handed to the three tests: they all read the same card the visitor is filtering
            $state = $this->productStateService->getState($product);

            return $this->matchesPrice($state, $filters['price'])
                && $this->matchesFormat($state, $filters['format'])
                && $this->matchesStock($state, $filters['stock']);
        }));
    }

    private function matchesPrice(array $state, ?string $bracket): bool
    {
        if (null === $bracket) {
            return true;
        }

        // A product with no item at all has no price to compare, so it is not in any band
        if (null === $state['price']) {
            return false;
        }

        [$min, $max] = explode('-', $bracket);

        return $state['price'] >= (int) $min && ('' === $max || $state['price'] < (int) $max);
    }

    private function matchesFormat(array $state, ?string $format): bool
    {
        return null === $format || in_array('label.' . $format, $state['formats'], true);
    }

    // "In stock" is what the card itself calls in stock: nothing sold out, and no release still ahead
    private function matchesStock(array $state, ?string $stock): bool
    {
        return null === $stock || (!$state['soldOut'] && null === $state['availableAt']);
    }

    // Orders the products on the lowest price of their items, the ones carrying no item at all closing the list whichever way it is ordered rather than leading it at price zero
    private function sortByPrice(array $products, bool $descending): array
    {
        $priced = [];
        $unpriced = [];

        foreach ($products as $product) {
            $price = $this->productStateService->getLowestPrice($product);
            if (null === $price) {
                $unpriced[] = $product;
            } else {
                $priced[] = [$price, $product];
            }
        }

        usort($priced, fn (array $a, array $b) => $descending ? $b[0] <=> $a[0] : $a[0] <=> $b[0]);

        return array_merge(array_column($priced, 1), $unpriced);
    }
}
