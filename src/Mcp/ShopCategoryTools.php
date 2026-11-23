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
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;

// The product categories on the site's MCP endpoint, as on their back office screen (see ProductCategoryCrudController): listed with their products, read, an empty one added and its texts and slug written, their order and blocks left to a human
class ShopCategoryTools
{
    // Categories a list gives at most
    public const int LIMIT = 100;

    public function __construct(
        private readonly ProductCategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductUpdater $productUpdater,
        private readonly ShopMcpAccess $access,
    ) {
    }

    // The tools of the categories, under the shop key of ShopMcpProvider
    /** @return iterable<McpTool> */
    public function getMcpTools(): iterable
    {
        yield new McpTool('shop_category_list', 'Product categories', 'The categories whose name holds the search words, every one without them, in the shop\'s order, each with its number of products and whether the shop lists it (it holds a product on sale).', $this->list(...));
        yield new McpTool('shop_category_get', 'Product category', 'A category: name, slug, description, listed or not, and its products with their id, title and hidden state, read only.', $this->get(...));
        yield new McpTool('shop_category_create', 'Add a category', 'Adds a category with its name and description, holding no product: the shop lists it nowhere until a product on sale is filed under it with shop_edit.', $this->create(...), true);
        yield new McpTool('shop_category_edit', 'Write a category', 'Changes only the name, description or slug given of a category, as the back office does: checked by its rules, and a new slug redirecting the old address. An edit of a category already listed shows on the site at once.', $this->edit(...), true);
    }

    // The categories whose name holds the words, every one without them, each with its products counted and the shop's listing told
    /**
     * @param ?string $search words looked for in the names
     *
     * @return list<array{id: ?int, name: ?string, slug: ?string, listed: bool, products: int}>
     */
    public function list(?string $search = null): array
    {
        $this->access->guard();
        $listed = $this->listedIds();

        return array_map(fn (array $row): array => [
            ...$this->summary($row[0], $listed),
            'products' => (int) $row['products'],
        ], $this->categoryRepository->findWithProductCount($search, self::LIMIT));
    }

    // A category with its description and its products, the trash and the templates left aside
    /**
     * @param int $category id of the category
     *
     * @return array{id: ?int, name: ?string, slug: ?string, listed: bool, description: ?string, products: list<array{id: ?int, title: ?string, hidden: bool}>}
     */
    public function get(int $category): array
    {
        $found = $this->found($category);
        $products = array_filter($found->getProducts()->toArray(), static fn (Product $product): bool => !$product->isDeleted() && !$product->isTemplate());

        return [
            ...$this->summary($found, $this->listedIds()),
            'description' => $found->getUntranslated('description'),
            'products' => array_values(array_map(static fn (Product $product): array => [
                'id' => $product->getId(),
                'title' => $product->getUntranslated('title'),
                'hidden' => $product->isHidden(),
            ], $products)),
        ];
    }

    // An empty category added at position 0 as a blank position field leaves it, its slug made from its name and kept unique by a number, checked as the back office's form checks it
    /**
     * @param string  $name        name of the category, 100 characters at most
     * @param ?string $description what its page says of itself, html allowed
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function create(string $name, ?string $description = null): array
    {
        $this->access->guard();
        $category = new ProductCategory()
            ->setName(trim($name))
            ->setSlug($this->access->unique(trim($name), 'category', fn (string $candidate): bool => null !== $this->categoryRepository->findOneBy(['slug' => $candidate])))
            ->setDescription($this->text($description))
            ->setPosition(0);
        $this->access->validate($category);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return ['id' => $category->getId(), 'slug' => $category->getSlug()];
    }

    // A category's name, description or slug changed, only those given, saved as the back office saves it: checked by its constraints, and its old url redirected when the slug changes
    /**
     * @param int     $category    id of the category
     * @param ?string $name        new name, 100 characters at most
     * @param ?string $description new description, html allowed, empty to remove it
     * @param ?string $slug        new slug, which moves the category's address and redirects the old one
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function edit(int $category, ?string $name = null, ?string $description = null, ?string $slug = null): array
    {
        $found = $this->found($category);

        // A call naming no field would write nothing
        if (null === $name && null === $description && null === $slug) {
            throw new ToolCallException('Nothing to change: give a name, a description or a slug.');
        }
        if (null !== $name) {
            $found->setName(trim($name));
        }
        if (null !== $description) {
            $found->setDescription($this->text($description));
        }
        if (null !== $slug) {
            $found->setSlug($this->access->normalized($slug));
        }
        $this->access->validate($found, true);
        $this->productUpdater->updateCategory($found);
        $this->entityManager->flush();

        return ['id' => $found->getId(), 'slug' => $found->getSlug()];
    }

    // A category by its id
    private function found(int $id): ProductCategory
    {
        $this->access->guard();
        $category = $this->categoryRepository->find($id);
        if (!$category instanceof ProductCategory) {
            throw new ToolCallException(sprintf('No category %d.', $id));
        }

        return $category;
    }

    // The ids of the categories the shop lists, those holding a product on sale, read once per call
    /** @return list<?int> */
    private function listedIds(): array
    {
        return array_map(static fn (ProductCategory $category): ?int => $category->getId(), $this->productRepository->findListedCategories());
    }

    // A category in a list, in the language it was written in
    /**
     * @param list<?int> $listed
     *
     * @return array{id: ?int, name: ?string, slug: ?string, listed: bool}
     */
    private function summary(ProductCategory $category, array $listed): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getUntranslated('name'),
            'slug' => $category->getSlug(),
            'listed' => in_array($category->getId(), $listed, true),
        ];
    }

    // A description trimmed, none for an empty one, the column being nullable
    private function text(?string $description): ?string
    {
        $description = trim((string) $description);

        return '' === $description ? null : $description;
    }
}
