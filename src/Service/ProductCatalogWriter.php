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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\ProductCatalogWriterInterface;
use c975L\UiBundle\Model\CatalogProduct;
use c975L\UiBundle\Model\CatalogProductItem;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The shop's side of ProductCatalogWriterInterface: another bundle's catalog writes its products here, each found again under the key it was written with. The files are copied in through Vich, the source left where it is (see ReplacingFile), so the shop keeps a file of its own
#[AsAlias(ProductCatalogWriterInterface::class)]
class ProductCatalogWriter implements ProductCatalogWriterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly ProductItemRepository $productItemRepository,
        private readonly SluggerInterface $slugger,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Writes the catalog's product and its items, see ProductCatalogWriterInterface
    public function write(CatalogProduct $catalogProduct): void
    {
        $product = $this->productRepository->findOneBy(['catalogKey' => $catalogProduct->key]);
        // Nothing to sell and nothing sold yet: no empty product is made
        if (null === $product && [] === $catalogProduct->items) {
            return;
        }
        $product ??= $this->create($catalogProduct);

        $keys = [];
        foreach ($catalogProduct->items as $catalogItem) {
            $this->writeItem($product, $catalogItem);
            $keys[] = $catalogItem->key;
        }

        // An item the catalog no longer sells is set aside, never deleted: the orders and downloads already made point at it
        foreach ($product->getItems() as $item) {
            if (null !== $item->getCatalogKey() && !\in_array($item->getCatalogKey(), $keys, true)) {
                $item->setHidden(true);
            }
        }

        $this->entityManager->flush();
    }

    // Every item holding a file still on disk, for a one-shot import into the catalog
    public function itemsWithFile(): array
    {
        $items = [];
        foreach ($this->productItemRepository->findWithFile() as $item) {
            $file = $item->getFile();
            $path = $this->projectDir . '/' . $file?->getPrivateDirectory() . '/' . $file?->getName();
            if (!is_file($path)) {
                continue;
            }

            $items[] = new CatalogProductItem(
                key: $item->getCatalogKey(),
                title: (string) $item->getTitle(),
                filePath: $path,
                price: $item->getPrice(),
                currency: strtoupper($item->getCurrency()),
                id: $item->getId(),
                slug: $item->getSlug(),
                productSlug: $item->getProduct()?->getSlug(),
                productTitle: $item->getProduct()?->getTitle(),
            );
        }

        return $items;
    }

    // Stamps the keys an import matched, so that the next write() finds the rows - a key another row already carries is left there, the import going on with this one unlinked
    public function setKeys(int $itemId, string $productKey, string $itemKey): void
    {
        $item = $this->productItemRepository->find($itemId);
        if (null === $item) {
            return;
        }

        if (\in_array($this->productItemRepository->findOneBy(['catalogKey' => $itemKey]), [null, $item], true)) {
            $item->setCatalogKey($itemKey);
        }
        $product = $item->getProduct();
        if (null !== $product && \in_array($this->productRepository->findOneBy(['catalogKey' => $productKey]), [null, $product], true)) {
            $product->setCatalogKey($productKey);
        }
        $this->entityManager->flush();
    }

    // A product the catalog writes for the first time, hidden as any new product is until the editor shows it - its title, its text and its picture its own from then on
    private function create(CatalogProduct $catalogProduct): Product
    {
        $product = new Product()
            ->setCatalogKey($catalogProduct->key)
            ->setTitle(mb_substr($catalogProduct->title, 0, 100))
            ->setSlug($this->freeSlug($catalogProduct->title))
            ->setDescription($catalogProduct->description);

        if (null !== $catalogProduct->coverPath && is_file($catalogProduct->coverPath)) {
            $media = new ProductMedia();
            $media->setFile(new ReplacingFile($catalogProduct->coverPath));
            $product->addMedia($media);
        }

        $this->entityManager->persist($product);

        return $product;
    }

    // The item written under the catalog's key, created when missing, moved here when another product holds it: its price follows the catalog's, its file is copied in again only when it changed - in size, or since it was copied. Its visibility stays the editor's
    private function writeItem(Product $product, CatalogProductItem $catalogItem): void
    {
        // The key is unique over the whole table, so it is looked for there: a product key the catalog changed brings its items along rather than writing them twice. addItem() only, as removeItem() would delete the row (orphanRemoval)
        $item = $this->productItemRepository->findOneBy(['catalogKey' => $catalogItem->key]);
        if (null !== $item) {
            $product->addItem($item);
        }

        // A new item gets its file and its slug before it is persisted: Vich uploads in prePersist, and builds its path from both slugs
        if (null === $item) {
            $file = new ProductItemFile();
            $file->setFile(new ReplacingFile($catalogItem->filePath));
            $item = new ProductItem()
                ->setCatalogKey($catalogItem->key)
                ->setTitle(mb_substr($catalogItem->title, 0, 50))
                ->setSlug($this->slugger->slug(mb_substr($catalogItem->title, 0, 50))->lower()->toString())
                ->setDescription($catalogItem->title)
                ->setPrice($catalogItem->price ?? 0)
                ->setCurrency(strtolower($catalogItem->currency))
                ->setFile($file);
            $product->addItem($item);
            $this->entityManager->persist($item);

            return;
        }

        $item->setCurrency(strtolower($catalogItem->currency));
        if (null !== $catalogItem->price) {
            $item->setPrice($catalogItem->price);
        }

        $file = $item->getFile() ?? new ProductItemFile();
        $item->setFile($file);
        $current = $this->projectDir . '/' . $file->getPrivateDirectory() . '/' . $file->getName();
        // Compared on size and date rather than read through: the copy is never newer than the catalog's file it was made of, and a recording runs to hundreds of megabytes on every save of the book
        if (null === $file->getName() || !is_file($current) || filesize($current) !== filesize($catalogItem->filePath) || filemtime($catalogItem->filePath) > filemtime($current)) {
            $file->setFile(new ReplacingFile($catalogItem->filePath));
        }
    }

    // The title's slug, numbered when another product already answers to it
    private function freeSlug(string $title): string
    {
        return UniqueSlug::build(
            $this->slugger,
            mb_substr($title, 0, 90),
            fn (string $candidate): bool => null !== $this->productRepository->findOneBy(['slug' => $candidate]),
        );
    }
}
