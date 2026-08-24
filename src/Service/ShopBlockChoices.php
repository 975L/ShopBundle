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
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use Symfony\Contracts\Service\ResetInterface;

// The two lists the block forms of this bundle pick from, in one place rather than in each of the six types offering one. The slug is stored, not the id: it is this bundle's natural key everywhere else (public urls, sitemap, import matching), so a block survives an export/import to another site the same way a product does
class ShopBlockChoices implements ResetInterface
{
    /** @var ?array<string, string> */
    private ?array $products = null;

    /** @var ?array<string, string> */
    private ?array $categories = null;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    // Read once per request: EasyAdmin renders one embedded form per block, so a sheet holding ten of them would otherwise run the same query ten times
    public function products(): array
    {
        return $this->products ??= $this->choices(
            $this->productRepository->findNotDeleted(),
            static fn (Product $product): string => (string) $product->getTitle(),
            static fn (Product $product): string => (string) $product->getSlug(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        return $this->categories ??= $this->choices(
            $this->categoryRepository->findAll(),
            static fn (ProductCategory $category): string => (string) $category->getName(),
            static fn (ProductCategory $category): string => (string) $category->getSlug(),
        );
    }

    // Choices are keyed by label, so two rows sharing a title would collapse into one entry and silently drop the other from the list: a duplicated title is disambiguated by the slug, which is unique by definition
    /**
     * @param iterable<object> $rows
     *
     * @return array<string, string>
     */
    private function choices(iterable $rows, callable $label, callable $slug): array
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);
        $counts = array_count_values(array_map($label, $rows));

        $choices = [];
        foreach ($rows as $row) {
            $title = $label($row);
            $key = $counts[$title] > 1 ? $title . ' (' . $slug($row) . ')' : $title;
            $choices[$key] = $slug($row);
        }

        return $choices;
    }

    // The lists only ever describe the screen being rendered - dropped between two requests so a worker runtime (FrankenPHP, RoadRunner...) doesn't offer the next one the catalog of the previous
    public function reset(): void
    {
        $this->products = null;
        $this->categories = null;
    }
}
