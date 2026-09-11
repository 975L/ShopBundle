<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use c975L\ShopBundle\Service\ShopTranslatedLocales;

// Declares the shop, its products and its categories (public/sitemap-shop.xml) for ConfigBundle's SitemapWriter - the products and the categories also carry a 'title' and a 'description', ignored by the sitemap and turned into llms.txt's "Shop" section, the shop's own url deliberately carrying none
class ShopSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly ShopPublicUrlResolver $shopPublicUrlResolver,
        private readonly ShopTranslatedLocales $shopTranslatedLocales,
        private readonly ProductServiceInterface $productService,
        private readonly ProductCategoryServiceInterface $productCategoryService,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'shop';
    }

    // A sitemap only accepts absolute urls, so there's nothing to declare before "site-url" is configured - which is what the resolver answers null for. Every url is built through it rather than by hand, so the sitemap can never drift from the routes themselves, and each screen is declared once per language it answers in: a language's url is only ever crawled if the sitemap names it, and the group alone leaves the other languages undeclared (see SitePageSitemapProvider, whose reading this follows)
    public function getUrls(): array
    {
        $shopUrl = $this->shopPublicUrlResolver->resolve('shop_index');
        if (null === $shopUrl) {
            return [];
        }

        // Urls for the shop
        $urls = $this->localized([
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 10,
        ], $shopUrl, $this->shopPublicUrlResolver->resolveAlternates('shop_index', [], $this->shopTranslatedLocales->forShop()));

        // Urls for products
        foreach ($this->productService->findAll() as $product) {
            $parameters = ['slug' => $product->getSlug()];
            $url = $this->shopPublicUrlResolver->resolve('product_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d', $product->getModification()->getTimestamp()),
                'changefreq' => 'weekly',
                'priority' => 8,
                'title' => (string) $product->getTitle(),
                // Passed as it is: the writer strips the markup, flattens it to a line and truncates it
                'description' => (string) $product->getDescription(),
            ], $url, $this->shopPublicUrlResolver->resolveAlternates('product_display', $parameters, $this->shopTranslatedLocales->forProduct($product)))];
        }

        // Urls for categories
        foreach ($this->productCategoryService->findAll() as $category) {
            $parameters = ['slug' => $category->getSlug()];
            $url = $this->shopPublicUrlResolver->resolve('category_display', $parameters);
            if (null === $url) {
                continue;
            }

            $urls = [...$urls, ...$this->localized([
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => 7,
                // A category has a name and nothing else to say - the description is left out rather than repeated from its name
                'title' => (string) $category->getName(),
            ], $url, $this->shopPublicUrlResolver->resolveAlternates('category_display', $parameters, $this->shopTranslatedLocales->forCategory($category)))];
        }

        return $urls;
    }

    // The same screen once per language, each entry carrying the whole group. The writing language comes first (see SiteLocales::all()), so the entry a single-language site has always had stays byte for byte the first one
    /**
     * @param array<string, mixed>  $url
     * @param array<string, string> $alternates
     *
     * @return list<array<string, mixed>>
     */
    private function localized(array $url, string $canonical, array $alternates): array
    {
        return array_map(
            static fn (string $loc): array => ['loc' => $loc, ...$url, 'alternates' => $alternates],
            [] === $alternates ? [$canonical] : array_values($alternates),
        );
    }
}
