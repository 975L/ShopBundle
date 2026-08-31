<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Management\BlockDataExporter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Serializes Products (pictures, items with the files customers download, blocks and categories, real files bundled in the archive) into the shape ContentExporter/ProductImportProvider expect, shared by ProductCrudController::exportSelection() and exportAll() below - a flat table dump carries one table at a time, where a product only means anything with its items and medias around it
class ProductExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly BlockDataExporter $blockDataExporter,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return ProductImportProvider::KIND;
    }

    // findBy() rather than the repository's own findAll(), which only answers what the shop stands behind (see ProductRepository::findAllSorted): a sync carries the hidden products being written and the recycle bin too, both of them work an admin would not expect an export to drop
    public function exportAll(): array
    {
        return $this->serialize($this->productRepository->findBy([], ['position' => 'ASC']));
    }

    /**
     * @param iterable<Product> $products
     */
    public function serialize(iterable $products): array
    {
        $files = [];
        $items = [];
        foreach ($products as $product) {
            $items[] = $this->exportProductData($product, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    // The "user" who wrote the product is deliberately left out, here as on every other export of the ecosystem: App\Entity\User ids never match between two environments
    private function exportProductData(Product $product, array &$files): array
    {
        $medias = [];
        foreach ($product->getMedias() as $media) {
            $mediaData = $this->exportMediaData($media, 'public', $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        $productItems = [];
        foreach ($product->getItems() as $item) {
            $productItems[] = $this->exportItemData($item, $files);
        }

        $categories = [];
        foreach ($product->getCategories() as $category) {
            // The name travels beside the slug so a category this environment doesn't hold yet is created readable rather than named after its own slug (see ProductImportProvider::resolveCategory)
            $categories[] = ['slug' => $category->getSlug(), 'name' => $category->getName()];
        }

        $relatedProducts = [];
        foreach ($product->getRelatedProducts() as $related) {
            $relatedProducts[] = $related->getSlug();
        }

        return [
            'slug' => $product->getSlug(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'brand' => $product->getBrand(),
            'position' => $product->getPosition(),
            'hidden' => $product->isHidden(),
            // The archive is a faithful copy: a product exported out of the recycle bin comes back to the recycle bin, not into the catalogue
            'isDeleted' => $product->isDeleted(),
            'availableAt' => $product->getAvailableAt()?->format(\DateTimeInterface::ATOM),
            // What the card this product sells is printed with, its amounts travelling on the items below
            'giftCardText' => $product->getGiftCardText(),
            'giftCardScratch' => $product->hasGiftCardScratch(),
            // What the "new" badge and the "newest first" listing are counted from (see ProductStateService), and the one date an import has to put back: the modification one is stamped by ProductListener on every flush, an import included
            'creation' => $product->getCreation()?->format(\DateTimeInterface::ATOM),
            'categories' => $categories,
            'relatedProducts' => $relatedProducts,
            // The product sheet's own composition, carried the same way SiteBundle's PageExportProvider carries a Page's, its own medias joining the archive
            'blocks' => $this->blockDataExporter->exportBlocks($product->getBlocks(), $files),
            'medias' => $medias,
            'items' => $productItems,
        ];
    }

    // An item's picture and its downloadable file are one-to-one placeholders created with it (see ProductItemListener::prePersist), so they are exported as keys that may hold nothing rather than as entries that may be missing
    private function exportItemData(ProductItem $item, array &$files): array
    {
        $media = $item->getMedia();
        $file = $item->getFile();

        return [
            'slug' => $item->getSlug(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'price' => $item->getPrice(),
            'priceBefore' => $item->getPriceBefore(),
            'currency' => $item->getCurrency(),
            'sku' => $item->getSku(),
            'gtin' => $item->getGtin(),
            'weight' => $item->getWeight(),
            'vat' => $item->getVat(),
            'limitedQuantity' => $item->getLimitedQuantity(),
            'orderedQuantity' => $item->getOrderedQuantity(),
            'service' => $item->isService(),
            // The archive is a faithful copy here too: an item exported offline comes back offline
            'hidden' => $item->isHidden(),
            'itemCondition' => $item->getItemCondition(),
            'giftCardValue' => $item->getGiftCardValue(),
            'position' => $item->getPosition(),
            'creation' => $item->getCreation()?->format(\DateTimeInterface::ATOM),
            'media' => null !== $media ? $this->exportMediaData($media, 'public', $files) : null,
            // The one file of the catalogue nothing else brings back: what a customer has paid for lives outside the document root and is never redeployed by a git clone (see ShopBackupPathProvider)
            'file' => null !== $file ? $this->exportMediaData($file, $file->getPrivateDirectory(), $files) : null,
        ];
    }

    // Registers the media's physical file for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a reference rather than its bytes - null for a placeholder holding no file and for a name whose file has left the disk, an archive never pointing at bytes it doesn't carry
    private function exportMediaData(Media $media, string $root, array &$files): ?array
    {
        $name = $media->getName();
        if (null === $name) {
            return null;
        }

        $path = $this->projectDir . '/' . $root . '/' . $name;
        if (!is_file($path)) {
            return null;
        }

        // The random prefix keeps the same-named files of two products apart, an archive laying every file of every product in one flat directory
        $archivePath = 'files/' . bin2hex(random_bytes(8)) . '_' . basename($name);
        $files[$archivePath] = $path;

        return [
            // The name the file is served under, exported beside its bytes so the import can put it back at the very same url instead of letting Vich name it again - a stored name carries a uniqid, so a synced catalogue used to answer at different image urls on every site it was carried to (see ProductImportProvider::archivedName)
            'name' => $name,
            'alt' => $media->getAlt(),
            'position' => $media->getPosition(),
            // Vich no longer stamping the imported media with a date of its own, this is the only thing left to date it by
            'updatedAt' => $media->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'file' => $archivePath,
        ];
    }
}
