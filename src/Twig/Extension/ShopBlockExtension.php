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
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductRecommendationServiceInterface;
use c975L\UiBundle\Entity\Block;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

// Resolves, at render time, what the block templates of this bundle display - a Block only ever stores what to show (a category slug, a maximum), never the products themselves, so a block never goes stale against the catalog. Same split as BookBundle's BookBlockExtension and GalleryBundle's GalleryBlockExtension
class ShopBlockExtension implements ResetInterface
{
    // The two routes a product sheet is served under, and the ones a block left without a slug reads its product from - the preview joins the public one so a draft's sheet is composed and read before it goes online
    private const array PRODUCT_ROUTES = ['product_display', 'product_preview'];

    /** @var ?list<Product> */
    private ?array $products = null;

    /** @var ?list<ProductCategory> */
    private ?array $categories = null;

    /** @var array<string, ?Product> */
    private array $bySlug = [];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $categoryRepository,
        private readonly ProductRecommendationServiceInterface $recommendationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * A null category is the whole shop, which is what the block form's empty choice stores.
     * Shuffling happens before the maximum applies, so "4 products at random" draws from the whole catalog and not from its first four - a block asking for it declines its own cache entry (see ShopBlockCacheTagProvider), so a new draw is made at every render.
     *
     * @return list<Product>
     */
    #[AsTwigFunction('shop_block_products')]
    public function getProducts(?string $categorySlug = null, ?int $max = null, bool $random = false): array
    {
        $products = null !== $categorySlug && '' !== $categorySlug
            ? $this->productRepository->findByCategorySlug($categorySlug)
            : $this->loadProducts();

        if ($random) {
            shuffle($products);
        }

        return null !== $max ? \array_slice($products, 0, $max) : $products;
    }

    /**
     * The visuals a card can be bought on, i.e. the products at least one item of which is money bought in advance (see Product::isGiftCard()).
     *
     * Filtered off the catalogue already read for this request rather than queried again: findAllSorted() joins the items, so the answer costs nothing beyond the loop.
     *
     * @return list<Product>
     */
    #[AsTwigFunction('shop_block_gift_cards')]
    public function getGiftCards(?int $max = null): array
    {
        $giftCards = array_values(array_filter($this->loadProducts(), static fn (Product $product): bool => $product->isGiftCard()));

        return null !== $max ? \array_slice($giftCards, 0, $max) : $giftCards;
    }

    /**
     * @return list<ProductCategory>
     */
    #[AsTwigFunction('shop_block_categories')]
    public function getCategories(?int $max = null): array
    {
        $categories = $this->loadCategories();

        return null !== $max ? \array_slice($categories, 0, $max) : $categories;
    }

    // An empty slug is what the kinds composing a product sheet store: they show the product the sheet is about, read from the request, and render nothing anywhere else. A slug pointing at a deleted or renamed product answers null the same way, its template then rendering nothing rather than half a card
    #[AsTwigFunction('shop_block_product')]
    public function getProduct(?string $slug = null): ?Product
    {
        $named = null !== $slug && '' !== $slug;
        $slug = $named ? $slug : $this->currentSlug();

        if (null === $slug) {
            return null;
        }

        // A product named by a block has to stand on its own, a draft or a trashed one rendering nothing, where the sheet's own product is read whatever its state so a draft's preview shows its blocks - the key says which reading it was, both being cached under the same slug
        $key = ($named ? 'published:' : 'any:') . $slug;

        return $this->bySlug[$key] ??= $named
            ? $this->productRepository->findOnePublishedBySlug($slug)
            : $this->productRepository->findOneBySlug($slug);
    }

    /**
     * The affinity already computed by c975l:shop:affinity:calculate, read through the same service the product sheet uses.
     *
     * @return list<Product>
     */
    #[AsTwigFunction('shop_block_recommendations')]
    public function getRecommendations(?string $slug = null, int $max = 4): array
    {
        $product = $this->getProduct($slug);

        return null !== $product ? $this->recommendationService->getSimilarProducts($product, $max) : [];
    }

    /**
     * Every kind held by a sheet, its containers' slots included: what a hardcoded section of product/display.html.twig reads to step aside when the editor has placed the block taking it over.
     * Two levels of slots, which is as deep as a container of a container goes.
     *
     * @param iterable<Block> $blocks
     *
     * @return list<string>
     */
    #[AsTwigFunction('shop_block_sheet_kinds')]
    public function getSheetKinds(iterable $blocks): array
    {
        $kinds = [];
        foreach ($blocks as $block) {
            $kinds[] = (string) $block->getKind();
            foreach ($block->getSlots() as $slot) {
                $kinds[] = (string) $slot->getKind();
                foreach ($slot->getSlots() as $nested) {
                    $kinds[] = (string) $nested->getKind();
                }
            }
        }

        return array_values(array_unique($kinds));
    }

    // The sheet's own product, and only there: a block left without a slug on a page that is not a product sheet has no product to show
    private function currentSlug(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !\in_array($request->attributes->get('_route'), self::PRODUCT_ROUTES, true)) {
            return null;
        }

        $slug = $request->attributes->get('slug');

        return \is_string($slug) && '' !== $slug ? $slug : null;
    }

    // The whole sorted catalog is read once per request, every block then slicing it in PHP: a page composed of several product blocks would otherwise issue the same query once per block
    /** @return list<Product> */
    private function loadProducts(): array
    {
        return $this->products ??= $this->productRepository->findAllSorted();
    }

    /** @return list<ProductCategory> */
    private function loadCategories(): array
    {
        return $this->categories ??= $this->categoryRepository->findAll();
    }

    // The lists only ever describe the request being rendered - dropped between two of them so a worker runtime (FrankenPHP, RoadRunner...) doesn't serve the next one the catalog of the previous
    public function reset(): void
    {
        $this->products = null;
        $this->categories = null;
        $this->bySlug = [];
    }
}
