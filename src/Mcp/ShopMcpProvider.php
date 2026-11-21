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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductStateServiceInterface;
use c975L\ShopBundle\Service\ProductUpdater;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// The shop's products on the site's MCP endpoint, for an account that manages them on the back office: listed, read with their items, a hidden product added and its texts and slug written as the back office writes them, prices, stock and publishing left to a human
class ShopMcpProvider implements McpToolProviderInterface
{
    // Products a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductStateServiceInterface $productStateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        private readonly SluggerInterface $slugger,
        private readonly ProductUpdater $productUpdater,
        private readonly ValidatorInterface $validator,
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
        return 'Products are the catalog of the shop, each sold through its items. A product added by an agent is hidden and has no item: a human prices it and puts it online from the back office.';
    }

    // The tools of the shop, the writing ones flagged
    public function getMcpTools(): iterable
    {
        yield new McpTool('shop_list', 'Products', 'The products whose title holds the search words, every product without them, hidden ones included, in the shop\'s order.', $this->list(...));
        yield new McpTool('shop_get', 'Product', 'A product: title, description, brand, categories, release day, hidden or not, and its items with their price in cents and stock (none for unlimited, 0 for withdrawn), read only.', $this->get(...));
        yield new McpTool('shop_create', 'Add a hidden product', 'Adds a product, hidden and without any item, with its title and description.', $this->create(...), true);
        yield new McpTool('shop_edit', 'Write a product', 'Changes only the title, description or slug given of a product, as the back office does: checked by its rules, and a new slug redirecting the old address.', $this->edit(...), true);
    }

    // The products whose title holds the words, every one without them, the trash and the templates left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<string>}>
     */
    public function list(?string $search = null): array
    {
        $this->guard();
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
     * @return array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<string>, description: ?string, brand: ?string, currency: string, items: list<array<string, mixed>>}
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
        $this->guard();
        $product = new Product()
            ->setTitle(trim($title))
            ->setSlug($this->slug(trim($title)))
            ->setDescription(trim($description))
            ->setHidden(true);
        $this->validate($product);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return ['id' => $product->getId(), 'slug' => $product->getSlug()];
    }

    // A product's title, description or slug changed, only those given, saved as the back office saves it: checked by its constraints, dated, and its old url redirected when the slug changes
    /**
     * @param int     $product     id of the product
     * @param ?string $title       new title, 100 characters at most
     * @param ?string $description new description, html allowed
     * @param ?string $slug        new slug, which moves the product's address and redirects the old one
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function edit(int $product, ?string $title = null, ?string $description = null, ?string $slug = null): array
    {
        $found = $this->found($product);

        // A call naming no field would only re-date the product, moving the sitemap's lastmod for nothing
        if (null === $title && null === $description && null === $slug) {
            throw new ToolCallException('Nothing to change: give a title, a description or a slug.');
        }
        if (null !== $title) {
            $found->setTitle(trim($title));
        }
        if (null !== $description) {
            $found->setDescription(trim($description));
        }
        if (null !== $slug) {
            $found->setSlug($this->normalized($slug));
        }
        $this->validate($found, true);
        $this->productUpdater->update($found);
        $this->entityManager->flush();

        return ['id' => $found->getId(), 'slug' => $found->getSlug()];
    }

    // The catalog is the admin's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-admin'))) {
            throw new ToolCallException('This account may not work on the products.');
        }
    }

    // The product's constraints, as the back office's form runs them, a refused edit taken back off the managed row
    private function validate(Product $product, bool $managed = false): void
    {
        $violations = $this->validator->validate($product);
        if (0 === count($violations)) {
            return;
        }
        if ($managed) {
            $this->entityManager->refresh($product);
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        throw new ToolCallException(implode(' ', $messages));
    }

    // A product by its id, one in the trash or a template answering as unknown
    private function found(int $id): Product
    {
        $this->guard();
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product || $product->isDeleted() || $product->isTemplate()) {
            throw new ToolCallException(sprintf('No product %d.', $id));
        }

        return $product;
    }

    // A product in a list
    /** @return array{id: ?int, title: ?string, slug: ?string, hidden: bool, available: ?string, categories: list<string>} */
    private function summary(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'slug' => $product->getSlug(),
            'hidden' => $product->isHidden(),
            'available' => $product->getAvailableAt()?->format('Y-m-d'),
            'categories' => array_values(array_map(static fn (ProductCategory $category): string => (string) $category->getName(), $product->getCategories()->toArray())),
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

    // A slug as an agent typed it, made a slug as the back office's slug field makes it, one made of signs only refused
    private function normalized(string $slug): string
    {
        $normalized = strtolower($this->slugger->slug(trim($slug))->toString());
        if ('' === $normalized) {
            throw new ToolCallException('The slug is empty.');
        }

        return $normalized;
    }

    // A slug from the title, "product" for a title made of signs only, a number added while another product holds it
    private function slug(string $title): string
    {
        $base = mb_substr($title, 0, 90);

        return UniqueSlug::build(
            $this->slugger,
            '' === $this->slugger->slug($base)->toString() ? 'product' : $base,
            fn (string $candidate): bool => null !== $this->productRepository->findOneBy(['slug' => $candidate]),
        );
    }
}
