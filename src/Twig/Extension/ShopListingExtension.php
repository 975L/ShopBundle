<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig\Extension;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopServiceInterface;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

// What the shop's listings read, called from inside their cached fragments (see shop/index.html.twig, category/display.html.twig) rather than by the controllers: a hit then queries nothing. Each is read once per request, the listing and its structured data being two fragments of the same page
class ShopListingExtension implements ResetInterface
{
    // The query parameters the listing reads, any other one being a stranger's (utm_*, fbclid...)
    private const array LISTING_PARAMETERS = ['order', 'price', 'format', 'stock', Paginator::PAGE_PARAMETER];

    // How long a fragment keyed on a query carrying a stranger's parameter lives
    private const int FOREIGN_QUERY_TTL = 3600;

    private ?Pagination $listing = null;

    /** @var array<string, list<Product>> */
    private array $byCategory = [];

    public function __construct(
        private readonly ShopServiceInterface $shopService,
        private readonly ProductServiceInterface $productService,
        private readonly ShopTranslator $shopTranslator,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The page of the index the request asks for - its order, its filters and its page number
    #[AsTwigFunction('shop_listing')]
    public function getListing(): Pagination
    {
        if (null === $this->listing) {
            $this->listing = $this->shopService->findAllProductsPaginated($this->query());
            $this->shopTranslator->apply($this->listing);
        }

        return $this->listing;
    }

    // What the index's fragments are keyed on: the whole query, sorted - the "next page" link carries it over as it came (see Paginator), so two queries differing in anything at all print two different links
    #[AsTwigFunction('shop_listing_key')]
    public function getListingKey(): string
    {
        $query = $this->query()->all();
        ksort($query);

        return hash('xxh128', (string) json_encode($query));
    }

    // How long the index's fragments live: for ever on a query the listing reads, an hour when it carries anything else - each such query being its own entry, one a crawler could otherwise multiply without end
    #[AsTwigFunction('shop_listing_ttl')]
    public function getListingTtl(): ?int
    {
        $foreign = array_diff(array_keys($this->query()->all()), self::LISTING_PARAMETERS);

        return [] === $foreign ? null : self::FOREIGN_QUERY_TTL;
    }

    #[AsTwigFunction('shop_categories_count')]
    public function countCategories(): int
    {
        return $this->shopService->countCategories();
    }

    /** @return list<array{value: string, min: int, max: ?int}> */
    #[AsTwigFunction('shop_price_brackets')]
    public function getPriceBrackets(): array
    {
        return $this->shopService->getPriceBrackets();
    }

    // The products filed under a category, in the language being read - through the repository rather than off the association: a category holds its hidden and its trashed products too, which the page would otherwise card up and link to a 404
    /** @return list<Product> */
    #[AsTwigFunction('shop_category_products')]
    public function getCategoryProducts(ProductCategory $category): array
    {
        $slug = (string) $category->getSlug();
        if (!\array_key_exists($slug, $this->byCategory)) {
            $products = array_values($this->productService->findByCategorySlug($slug));
            $this->shopTranslator->apply($products);
            $this->byCategory[$slug] = $products;
        }

        return $this->byCategory[$slug];
    }

    public function reset(): void
    {
        $this->listing = null;
        $this->byCategory = [];
    }

    private function query(): InputBag
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? new InputBag() : $request->query;
    }
}
