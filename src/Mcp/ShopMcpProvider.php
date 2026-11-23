<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Mcp;

use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductStateServiceInterface;
use c975L\ShopBundle\Service\ProductUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;

// The shop's products on the site's MCP endpoint, for an account that manages them on the back office: listed, read with their items, a hidden product added and its texts, slug and categories written as the back office writes them, prices, stock and publishing left to a human - the categories' tools joined from ShopCategoryTools
class ShopMcpProvider implements McpToolProviderInterface
{
    // Products a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductStateServiceInterface $productStateService,
        private readonly ProductCategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductUpdater $productUpdater,
        private readonly ShopMcpAccess $access,
        private readonly ShopCategoryTools $categoryTools,
    ) {
    }

    // Key of the shop for the agent tokens
    public function getMcpKey(): string
    {
        return 'shop';
    }

    // How the shop's tools go together
    public function getMcpInstructions(): string
    {
        return 'Products are the catalog of the shop, each sold through its items. A product added by an agent is hidden and has no item: a human prices it and puts it online from the back office. Categories group the products: a category added by an agent holds none and is listed nowhere until a product on sale is filed under it. An edit of a product or category already visible shows on the site at once.';
    }

    // The tools of the shop, the writing ones flagged
    public function getMcpTools(): iterable
    {
        yield new McpTool('shop_list', 'Products', 'The products whose title holds the search words, every product without them, hidden ones included, in the shop\'s order.', $this->list(...));
        yield new McpTool('shop_get', 'Product', 'A product: title, description, brand, categories with their id, release day, hidden or not, and its items with their price in cents and stock (none for unlimited, 0 for withdrawn), read only.', $this->get(...));
        yield new McpTool('shop_create', 'Add a hidden product', 'Adds a product, hidden and without any item, with its title and description.', $this->create(...), true);
        yield new McpTool('shop_edit', 'Write a product', 'Changes only the title, description, slug or categories given of a product, as the back office does: checked by its rules, a new slug redirecting the old address, the category ids given replacing its own, read with shop_get first. An edit of a product already visible shows on the site at once.', $this->edit(...), true);
        yield from $this->categoryTools->getMcpTools();
    }

    // The products whose title holds the words, every one without them, the trash and the templates left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<array{id: ?int, name: string}>}>
     */
    public function list(?string $search = null): array
    {
        $this->access->guard();
        $builder = $this->productRepository->createQueryBuilder('p')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.template = false')
            ->orderBy('p.position', \SortDirection::Ascending)
            ->setMaxResults(self::LIMIT);
        if ('' !== trim((string) $search)) {
            $builder->andWhere('p.title LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_\\') . '%');
        }

        return array_map($this->summary(...), $builder->getQuery()->getResult());
    }

    // A product with its description and every item, prices in cents of the shop's currency
    /**
     * @param int $product id of the product
     *
     * @return array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<array{id: ?int, name: string}>, description: ?string, brand: ?string, currency: string, items: list<array<string, mixed>>}
     */
    public function get(int $product): array
    {
        $found = $this->found($product);

        return [
            ...$this->summary($found),
            'description' => $found->getDescription(),
            'brand' => $found->getBrand(),
            'currency' => $this->productStateService->getCurrency(),
            'items' => array_values(array_map($this->item(...), $found->getItems()->toArray())),
        ];
    }

    // A hidden product added without any item, its slug made from its title and kept unique by a number, checked as the back office's form checks it
    /**
     * @param string $title       title of the product, 100 characters at most
     * @param string $description its description, html allowed
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function create(string $title, string $description): array
    {
        $this->access->guard();
        $product = new Product()
            ->setTitle(trim($title))
            ->setSlug($this->slug(trim($title)))
            ->setDescription(trim($description))
            ->setHidden(true);
        $this->access->validate($product);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return ['id' => $product->getId(), 'slug' => $product->getSlug()];
    }

    // A product's title, description, slug or categories changed, only those given, saved as the back office saves it: checked by its constraints, dated, and its old url redirected when the slug changes
    /**
     * @param int        $product     id of the product
     * @param ?string    $title       new title, 100 characters at most
     * @param ?string    $description new description, html allowed
     * @param ?string    $slug        new slug, which moves the product's address and redirects the old one
     * @param ?list<int> $categories  ids of every category the product is filed under, replacing its own, empty to take it out of all
     *
     * @return array{id: ?int, slug: ?string, categories: list<array{id: ?int, name: string}>}
     */
    public function edit(int $product, ?string $title = null, ?string $description = null, ?string $slug = null, ?array $categories = null): array
    {
        $found = $this->found($product);

        // A call naming no field would only re-date the product, moving the sitemap's lastmod for nothing
        if (null === $title && null === $description && null === $slug && null === $categories) {
            throw new ToolCallException('Nothing to change: give a title, a description, a slug or categories.');
        }
        $filed = null === $categories ? null : $this->categories($categories);
        if (null !== $title) {
            $found->setTitle(trim($title));
        }
        if (null !== $description) {
            $found->setDescription(trim($description));
        }
        if (null !== $slug) {
            $found->setSlug($this->access->normalized($slug));
        }
        $this->access->validate($found, true);
        if (null !== $filed) {
            $this->file($found, $filed);
        }
        $this->productUpdater->update($found);
        $this->entityManager->flush();

        return ['id' => $found->getId(), 'slug' => $found->getSlug(), 'categories' => $this->summary($found)['categories']];
    }

    // The categories of the ids given, an unknown id refusing the whole set before anything is changed
    /**
     * @param list<int> $ids
     *
     * @return list<ProductCategory>
     */
    private function categories(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        $categories = [] === $ids ? [] : $this->categoryRepository->findBy(['id' => $ids]);
        $unknown = array_diff($ids, array_map(static fn (ProductCategory $category): ?int => $category->getId(), $categories));
        if ([] !== $unknown) {
            throw new ToolCallException(sprintf('No category %s.', implode(', ', $unknown)));
        }

        return array_values($categories);
    }

    // The product filed under exactly the categories given, as the back office's categories field files it
    /** @param list<ProductCategory> $categories */
    private function file(Product $product, array $categories): void
    {
        foreach ($product->getCategories()->toArray() as $category) {
            if (!in_array($category, $categories, true)) {
                $product->removeCategory($category);
            }
        }
        foreach ($categories as $category) {
            $product->addCategory($category);
        }
    }

    // A product by its id, one in the trash or a template answering as unknown
    private function found(int $id): Product
    {
        $this->access->guard();
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product || $product->isDeleted() || $product->isTemplate()) {
            throw new ToolCallException(sprintf('No product %d.', $id));
        }

        return $product;
    }

    // A product in a list
    /** @return array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<array{id: ?int, name: string}>} */
    private function summary(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'slug' => $product->getSlug(),
            'hidden' => $product->isHidden(),
            'available' => $product->getAvailableAt()?->format('Y-m-d'),
            'categories' => array_values(array_map(static fn (ProductCategory $category): array => ['id' => $category->getId(), 'name' => (string) $category->getName()], $product->getCategories()->toArray())),
        ];
    }

    // An item as read only figures: a null stock is unlimited, 0 withdrawn, otherwise the cap the orders count against
    /** @return array{id: ?int, title: ?string, hidden: bool, price: ?int, priceBefore: ?int, vat: ?float, sku: ?string, stock: ?int, ordered: ?int} */
    private function item(ProductItem $item): array
    {
        return [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'hidden' => $item->isHidden(),
            'price' => $item->getPrice(),
            'priceBefore' => $item->getPriceBefore(),
            'vat' => $item->getVat(),
            'sku' => $item->getSku(),
            'stock' => $item->getLimitedQuantity(),
            'ordered' => $item->getOrderedQuantity(),
        ];
    }

    // A slug from the title, "product" for a title made of signs only, a number added while another product holds it
    private function slug(string $title): string
    {
        return $this->access->unique($title, 'product', fn (string $candidate): bool => null !== $this->productRepository->findOneBy(['slug' => $candidate]));
    }
}
