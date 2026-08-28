<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

// The made-up catalog held as plain data, read by ShopShowcaseProvider for the block showcase and by ShopDemoFixtureProvider for a demo site - titles are "shop" keys, prices in cents
class ShopSampleCatalog
{
    /**
     * Slug => name key, the three of them splitting the products the way the "format" filter does.
     *
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        return [
            'mobilier' => 'label.shop_sample_category_furniture',
            'decoration' => 'label.shop_sample_category_decoration',
            'numerique' => 'label.shop_sample_category_digital',
        ];
    }

    /**
     * @return list<array{slug: string, creation: string, title: string, description: string, category: string, items: list<array{slug: string, title: string, description: string, price: int, priceBefore: int|null, limitedQuantity: int|null, service: bool, file: string|null}>}>
     */
    public function getProducts(): array
    {
        return [
            [
                'slug' => 'table-basse-chene',
                'creation' => '2026-01-12',
                'title' => 'label.shop_sample_product_table_title',
                'description' => 'label.shop_sample_product_table_description',
                'category' => 'mobilier',
                'items' => [$this->item('table-basse-chene-standard', 'standard', 24900)],
            ],
            [
                'slug' => 'chaise-bistrot',
                'creation' => '2026-01-26',
                'title' => 'label.shop_sample_product_chair_title',
                'description' => 'label.shop_sample_product_chair_description',
                'category' => 'mobilier',
                // The one item on sale, and the one running out of stock, so both the filter and the price-before line have something to show
                'items' => [$this->item('chaise-bistrot-standard', 'standard', 8900, priceBefore: 11900, limitedQuantity: 4)],
            ],
            [
                'slug' => 'lampe-atelier',
                'creation' => '2026-02-09',
                'title' => 'label.shop_sample_product_lamp_title',
                'description' => 'label.shop_sample_product_lamp_description',
                'category' => 'decoration',
                'items' => [$this->item('lampe-atelier-standard', 'standard', 12900)],
            ],
            [
                'slug' => 'coussin-lin',
                'creation' => '2026-02-17',
                'title' => 'label.shop_sample_product_cushion_title',
                'description' => 'label.shop_sample_product_cushion_description',
                'category' => 'decoration',
                'items' => [$this->item('coussin-lin-standard', 'standard', 3900)],
            ],
            [
                // Two items, the one case a product sheet is worth opening for: the same content posted and downloaded
                'slug' => 'guide-amenagement',
                'creation' => '2026-03-02',
                'title' => 'label.shop_sample_product_guide_title',
                'description' => 'label.shop_sample_product_guide_description',
                'category' => 'numerique',
                'items' => [
                    $this->item('guide-amenagement-imprime', 'printed', 1900),
                    $this->item('guide-amenagement-pdf', 'download', 1200, file: 'guide-amenagement.pdf'),
                ],
            ],
            [
                'slug' => 'atelier-decoration',
                'creation' => '2026-03-16',
                'title' => 'label.shop_sample_product_workshop_title',
                'description' => 'label.shop_sample_product_workshop_description',
                'category' => 'numerique',
                'items' => [$this->item('atelier-decoration-seance', 'session', 6000, service: true)],
            ],
        ];
    }

    /**
     * Item titles are shared rather than written per product, the way a catalog repeats them from one sheet to the next.
     *
     * The slug is what the showcase carries: a persisted item has its own rewritten from its title by ProductItemListener.
     *
     * @return array{slug: string, title: string, description: string, price: int, priceBefore: int|null, limitedQuantity: int|null, service: bool, file: string|null}
     */
    private function item(
        string $slug,
        string $kind,
        int $price,
        ?int $priceBefore = null,
        ?int $limitedQuantity = null,
        bool $service = false,
        ?string $file = null,
    ): array {
        return [
            'slug' => $slug,
            'title' => 'label.shop_sample_item_' . $kind . '_title',
            'description' => 'label.shop_sample_item_' . $kind . '_description',
            'price' => $price,
            'priceBefore' => $priceBefore,
            'limitedQuantity' => $limitedQuantity,
            'service' => $service,
            'file' => $file,
        ];
    }
}
