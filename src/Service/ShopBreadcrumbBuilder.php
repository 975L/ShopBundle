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
use c975L\ShopBundle\Entity\ProductCategory;
use Symfony\Contracts\Translation\TranslatorInterface;

// The trail leading to a shop page, built once and read twice - the nav printed above the page and the BreadcrumbList a search engine shows - so the markup never claims a trail the visitor is not shown. Every level is read in the language the page around it is being read in: a visitor on "/en/shop/products/x" clicking the trail stays in English, and the graph describes the page he really has open
class ShopBreadcrumbBuilder
{
    public function __construct(
        private readonly ShopPublicUrlResolver $shopPublicUrlResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * The levels leading to a product's sheet, in reading order, the sheet itself last.
     *
     * @return list<array{name: string, url: string}>
     */
    public function forProduct(Product $product): array
    {
        $trail = [$this->shopLevel()];

        // The first category, as the graph's own "category" property already takes: a product filed under three of them has no single trail, and the first is the one its sheet prints first
        $category = $product->getCategories()->first();
        if (false !== $category) {
            $trail[] = $this->categoryLevel($category);
        }

        $trail[] = [
            'name' => trim((string) $product->getTitle()),
            'url' => $this->shopPublicUrlResolver->resolveLocalizedUrl('product_display', ['slug' => $product->getSlug()]),
        ];

        return $trail;
    }

    /**
     * The levels leading to a category's page, in reading order, the page itself last.
     *
     * @return list<array{name: string, url: string}>
     */
    public function forCategory(ProductCategory $category): array
    {
        return [$this->shopLevel(), $this->categoryLevel($category)];
    }

    // The shop's own front page, which is where every trail of this bundle starts - and not the site's home page, whose route belongs to whichever bundle draws the site, whereas this one runs without any of them
    private function shopLevel(): array
    {
        return [
            'name' => $this->translator->trans('label.shop', [], 'shop'),
            'url' => $this->shopPublicUrlResolver->resolveLocalizedUrl('shop_index'),
        ];
    }

    private function categoryLevel(ProductCategory $category): array
    {
        return [
            'name' => trim((string) $category->getName()),
            'url' => $this->shopPublicUrlResolver->resolveLocalizedUrl('category_display', ['slug' => $category->getSlug()]),
        ];
    }
}
