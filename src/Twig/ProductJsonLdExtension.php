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
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use c975L\ShopBundle\Service\ShopBreadcrumbBuilder;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

// A Twig function rather than a template of the bundle, on the model of UiBundle's contact_json_ld() and BookBundle's book_json_ld(): the markup belongs to the bundle, the theme to the site, and a site overriding product/display.html.twig keeps its structured data by calling the same function
class ProductJsonLdExtension
{
    public function __construct(
        private readonly ProductSnippetBuilder $snippetBuilder,
        private readonly ShopBreadcrumbBuilder $breadcrumbBuilder,
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Returns the <script type="application/ld+json"> payload for a product's sheet, empty when there is nothing to publish
    #[AsTwigFunction('product_json_ld', isSafe: ['html'])]
    public function productJsonLd(Product $product, ?string $imageUrl = null, ?string $url = null, bool $withRating = false): string
    {
        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildProduct($product, $imageUrl, $url, $this->itemImageUrls($product), $withRating));
    }

    /**
     * Returns the <script type="application/ld+json"> payload of a listing - the shop's index or a category page - empty when it prints no card.
     *
     * $offset is what the pages before this one already listed, the shop's index paginating: the template hands over the paginator's own figures rather than letting this guess at them.
     *
     * @param iterable<Product> $products the products the page shows, in the order it shows them
     */
    #[AsTwigFunction('shop_products_json_ld', isSafe: ['html'])]
    public function productsJsonLd(iterable $products, int $offset = 0): string
    {
        $listed = [];

        foreach ($products as $product) {
            $listed[] = [
                'name' => trim((string) $product->getTitle()),
                'url' => $this->urlGenerator->generate('product_display', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildItemList($listed, $offset));
    }

    // Returns the levels leading to a shop page, which the nav above it prints - the same ones the breadcrumb's own structured data is built from, so the two never disagree
    #[AsTwigFunction('shop_breadcrumb')]
    public function breadcrumb(Product | ProductCategory $subject): array
    {
        return $subject instanceof Product ? $this->breadcrumbBuilder->forProduct($subject) : $this->breadcrumbBuilder->forCategory($subject);
    }

    // Returns the <script type="application/ld+json"> payload of that same trail, in its own tag rather than merged into the product's graph: two separate nodes is what a search engine reads, and it leaves product_json_ld() as it was
    #[AsTwigFunction('shop_breadcrumb_json_ld', isSafe: ['html'])]
    public function breadcrumbJsonLd(Product | ProductCategory $subject): string
    {
        return $this->snippetBuilder->buildJson($this->snippetBuilder->buildBreadcrumb($this->breadcrumb($subject)));
    }

    // Every item's picture as an absolute url, keyed by its slug - the asset() the item's card applies to the same media, made absolute like the sheet does for the product's own image, and resolved here so the builder keeps knowing nothing about urls
    private function itemImageUrls(Product $product): array
    {
        $urls = [];

        foreach ($product->getVisibleItems() as $item) {
            $media = $item->getMedia();
            if (null !== $media && null !== $media->getName()) {
                $urls[trim((string) $item->getSlug())] = $this->urlHelper->getAbsoluteUrl($this->packages->getUrl($media->getName()));
            }
        }

        return $urls;
    }
}
