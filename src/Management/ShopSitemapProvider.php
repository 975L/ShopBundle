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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;

// Declares the shop, its products and its categories (public/sitemap-shop.xml) for ConfigBundle's SitemapWriter - the products and the categories also carry a 'title' and a 'description', ignored by the sitemap and turned into llms.txt's "Shop" section, the shop's own url deliberately carrying none
class ShopSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ProductServiceInterface $productService,
        private readonly ProductCategoryServiceInterface $productCategoryService,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'shop';
    }

    // A sitemap only accepts absolute urls, so there's nothing to declare before "site-url" is configured
    public function getUrls(): array
    {
        $urlRoot = rtrim((string) $this->configService->get('site-url'), '/');
        if ('' === $urlRoot) {
            return [];
        }

        // Urls for the shop
        $urls = [[
            'loc' => $urlRoot . '/shop',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 10,
        ]];

        // Urls for products
        foreach ($this->productService->findAll() as $product) {
            $urls[] = [
                'loc' => $urlRoot . '/shop/products/' . $product->getSlug(),
                'lastmod' => date('Y-m-d', $product->getModification()->getTimestamp()),
                'changefreq' => 'weekly',
                'priority' => 8,
                'title' => (string) $product->getTitle(),
                // Passed as it is: the writer strips the markup, flattens it to a line and truncates it
                'description' => (string) $product->getDescription(),
            ];
        }

        // Urls for categories
        foreach ($this->productCategoryService->findAll() as $category) {
            $urls[] = [
                'loc' => $urlRoot . '/shop/category/' . $category->getSlug(),
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => 7,
                // A category has a name and nothing else to say - the description is left out rather than repeated from its name
                'title' => (string) $category->getName(),
            ];
        }

        return $urls;
    }
}
