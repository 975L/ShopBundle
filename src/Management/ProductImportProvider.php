<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\MediaRepository;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "shop_product" content export (see ProductExportProvider), products matched by slug and items by slug within their product, never by id, dev and prod ids never matching - an item or a file the archive doesn't carry is left where it is rather than deleted, being what a customer has paid for, where a product's pictures are mirrored
class ProductImportProvider implements ImportProviderInterface
{
    public const string KIND = 'shop_product';

    // Where this bundle's uploads live, under public/ for the pictures and under private/ for the paid files (see ProductMedia/ProductItemFile::getVichMediaPath) - the one prefix an archived name is honoured under
    private const string MEDIA_DIRECTORY = 'medias/shop/';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly BlockDataImporter $blockDataImporter,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;
        // The products of this archive, keyed by slug: what the related-products pass below resolves against, a product naming one that is imported after it in the same batch
        $imported = [];
        // Resolved/created categories, keyed by slug - findOneBySlug() can't see a category created for a previous product of the same batch, which is only flushed at the end
        $categories = [];
        // Held back rather than set in the loop: ProductListener/ProductItemListener stamp a creation date on prePersist, which would overwrite anything written before the flush (see restoreCreationDates)
        $creationDates = [];

        foreach ($items as $item) {
            $product = $this->productRepository->findOneBySlug($item['slug']);
            $isNew = null === $product;
            $product ??= new Product();

            $this->writeProduct($product, $item, $filesDir, $categories, $creationDates);

            $this->em->persist($product);
            $imported[$item['slug']] = $product;
            $isNew ? $created++ : $updated++;
        }

        // Once every product of the archive is known, a sheet naming one that comes further down the manifest resolving as well as one that comes before it
        foreach ($items as $item) {
            $this->replaceRelatedProducts($imported[$item['slug']], $item['relatedProducts'] ?? [], $imported);
        }

        $this->em->flush();

        $this->restoreCreationDates($creationDates);

        return ['created' => $created, 'updated' => $updated];
    }

    // Everything one sheet of the archive says about its product, each collection replaced whole
    // @param array<string, \c975L\ShopBundle\Entity\ProductCategory> $categories
    // @param list<array{0: object, 1: \DateTime}> $creationDates
    private function writeProduct(Product $product, array $item, ?string $filesDir, array &$categories, array &$creationDates): void
    {
        $this->fillProduct($product, $item);
        $this->replaceCategories($product, $item['categories'] ?? [], $categories);
        $this->replaceBlocks($product, $item['blocks'] ?? [], $filesDir);
        $this->importMedias($product, $item['medias'] ?? [], $filesDir);
        $this->importItems($product, $item['items'] ?? [], $filesDir, $creationDates);

        if (isset($item['creation'])) {
            $creationDates[] = [$product, new \DateTime($item['creation'])];
        }
    }

    private function fillProduct(Product $product, array $item): void
    {
        $product
            ->setSlug($item['slug'])
            ->setTitle($item['title'])
            ->setDescription($item['description'] ?? '')
            ->setBrand($item['brand'] ?? null)
            ->setAge($item['age'] ?? null)
            ->setPosition($item['position'] ?? null)
            ->setAvailableAt(isset($item['availableAt']) ? new \DateTime($item['availableAt']) : null);

        $this->fillProductGiftCard($product, $item);
        $this->fillProductPublication($product, $item);
    }

    // An archive written before the card had a visual carries neither: the text stays empty and the panel stays on, which is the default a card is sold with
    private function fillProductGiftCard(Product $product, array $item): void
    {
        $product
            ->setGiftCardText($item['giftCardText'] ?? null)
            ->setGiftCardScratch($item['giftCardScratch'] ?? true);
    }

    // Hidden first, trashed second: trashing hides (see Product::setIsDeleted), and the other order would put a product of the recycle bin back into the catalogue
    // An archive written before the switch was turned round carries "isPublished" instead, and is read the same way rather than landing every product in the catalogue
    private function fillProductPublication(Product $product, array $item): void
    {
        $product
            ->setHidden($item['hidden'] ?? !($item['isPublished'] ?? false))
            ->setIsDeleted($item['isDeleted'] ?? false);
    }

    // The link table is written from the product's side, so the whole set is replaced - a category the archive names and this environment doesn't hold yet is created on the fly, exactly as CollectionItemImportProvider creates a collection it is handed
    private function replaceCategories(Product $product, array $categoriesData, array &$categories): void
    {
        foreach ($product->getCategories()->toArray() as $existingCategory) {
            $product->removeCategory($existingCategory);
        }

        foreach ($categoriesData as $categoryData) {
            $product->addCategory($this->resolveCategory($categoryData, $categories));
        }
    }

    private function resolveCategory(array $categoryData, array &$categories): ProductCategory
    {
        $slug = $categoryData['slug'];
        if (isset($categories[$slug])) {
            return $categories[$slug];
        }

        $category = $this->productCategoryRepository->findOneBySlug($slug);
        if (null === $category) {
            // Named after the archive, and after its own slug for an archive that carries no name - the category section of the same manifest writes the real one over it whichever order the two were imported in (see ProductCategoryImportProvider)
            $category = new ProductCategory()
                ->setSlug($slug)
                ->setName($categoryData['name'] ?? $slug);
            $this->em->persist($category);
        }

        return $categories[$slug] = $category;
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush, same as PageImportProvider
    private function replaceBlocks(Product $product, array $blocksData, ?string $filesDir): void
    {
        foreach ($product->getBlocks()->toArray() as $existingBlock) {
            $product->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $product->addBlock($block);
        }
    }

    // The product's own pictures, matched by the name they are served under: what the archive doesn't carry is dropped (orphanRemoval takes the row, Vich the file), a picture being the one thing here an admin puts back in a drag
    private function importMedias(Product $product, array $mediasData, ?string $filesDir): void
    {
        $existing = [];
        foreach ($product->getMedias() as $media) {
            $existing[(string) $media->getName()] = $media;
        }

        $kept = [];
        foreach ($mediasData as $mediaData) {
            $name = $this->archivedName($mediaData['name'] ?? null);
            $media = null !== $name && isset($existing[$name]) ? $existing[$name] : new ProductMedia();

            $this->fillMedia($media, $mediaData);
            $product->addMedia($media);
            $this->attachFile($media, $mediaData, $filesDir, 'public');
            $this->em->persist($media);

            $kept[spl_object_id($media)] = true;
        }

        foreach ($product->getMedias()->toArray() as $media) {
            if (!isset($kept[spl_object_id($media)])) {
                $product->removeMedia($media);
            }
        }
    }

    // The product's items, matched by slug - the slug itself is rewritten from the title on every flush (see ProductItemListener), so it is read here rather than written
    /**
     * @param list<array{0: Product|ProductItem, 1: \DateTime}> $creationDates
     */
    private function importItems(Product $product, array $itemsData, ?string $filesDir, array &$creationDates): void
    {
        $existing = [];
        foreach ($product->getItems() as $item) {
            $existing[(string) $item->getSlug()] = $item;
        }

        foreach ($itemsData as $itemData) {
            $slug = $itemData['slug'] ?? null;
            $item = null !== $slug && isset($existing[$slug]) ? $existing[$slug] : new ProductItem();

            $this->fillItem($item, $itemData);
            $product->addItem($item);
            $this->importItemMedia($item, $itemData, $filesDir);
            $this->em->persist($item);

            if (isset($itemData['creation'])) {
                $creationDates[] = [$item, new \DateTime($itemData['creation'])];
            }
        }
    }

    private function fillItem(ProductItem $item, array $itemData): void
    {
        $item
            ->setTitle($itemData['title'])
            ->setSlug($itemData['slug'] ?? '')
            ->setDescription($itemData['description'] ?? '');

        $this->fillItemCatalogue($item, $itemData);
        $this->fillItemPricing($item, $itemData);
        $this->fillItemStock($item, $itemData);

        // An archive written before the column existed carries items that were all on sale, which is what they come back as - and one written before the switch was turned round carries "isPublished", read the same way
        $item->setHidden($itemData['hidden'] ?? !($itemData['isPublished'] ?? true));
    }

    // How the item is presented beside the others: what kind of thing it is, and where it sits in the list
    private function fillItemCatalogue(ProductItem $item, array $itemData): void
    {
        $item
            ->setService($itemData['service'] ?? null)
            ->setItemCondition($itemData['itemCondition'] ?? null)
            // An archive written before the column existed carries items that weigh nothing, which is what they come back as
            ->setWeight($itemData['weight'] ?? null)
            ->setGiftCardValue($itemData['giftCardValue'] ?? null)
            ->setPosition($itemData['position'] ?? null);
    }

    // What the item is sold for, and what identifies it in a catalogue feed
    private function fillItemPricing(ProductItem $item, array $itemData): void
    {
        $item
            ->setPrice((int) ($itemData['price'] ?? 0))
            ->setPriceBefore($itemData['priceBefore'] ?? null)
            ->setCurrency($itemData['currency'] ?? 'eur')
            ->setVat((float) ($itemData['vat'] ?? 0))
            ->setSku($itemData['sku'] ?? null)
            ->setGtin($itemData['gtin'] ?? null);
    }

    // Carried like the rest: an archive is a copy of a shop at a moment, stock counters included
    private function fillItemStock(ProductItem $item, array $itemData): void
    {
        $item
            ->setLimitedQuantity($itemData['limitedQuantity'] ?? null)
            ->setOrderedQuantity($itemData['orderedQuantity'] ?? null);
    }

    // The item's picture and the file it is bought for, each written only when the archive carries one: a placeholder holding nothing (see ProductItemListener::prePersist) must not erase what this environment already serves
    private function importItemMedia(ProductItem $item, array $itemData, ?string $filesDir): void
    {
        $mediaData = $itemData['media'] ?? null;
        if (null !== $mediaData) {
            $media = $item->getMedia() ?? new ProductItemMedia();
            $this->fillMedia($media, $mediaData);
            $item->setMedia($media);
            $this->attachFile($media, $mediaData, $filesDir, 'public');
            $this->em->persist($media);
        }

        $fileData = $itemData['file'] ?? null;
        if (null === $fileData) {
            return;
        }

        $file = $item->getFile() ?? new ProductItemFile();
        $this->fillMedia($file, $fileData);
        $item->setFile($file);
        // Outside the document root, which is where the pipeline moves it too (see UiBundle's VichImageResizeListener::moveFileToPrivate)
        $this->attachFile($file, $fileData, $filesDir, $file->getPrivateDirectory());
        $this->em->persist($file);
    }

    private function fillMedia(Media $media, array $mediaData): void
    {
        $media
            ->setAlt($mediaData['alt'] ?? null)
            ->setPosition($mediaData['position'] ?? null);

        // The column is not nullable and nothing else writes it for a media whose file the archive turns out not to hold - attachFile() puts the exported date over this one as soon as there are bytes to date
        if (null === $media->getUpdatedAt()) {
            $media->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    // The two ways of putting a media's file back, decided by whether the archive names it - named, it is laid straight back under that name, Vich never seeing one, which keeps the image urls and spares the resizing; unnamed, Vich stores and names it anew, on the ReplacingFile technique a plain File would leave silently ignored
    private function attachFile(Media $media, array $mediaData, ?string $filesDir, string $root): void
    {
        if (null === $filesDir || !isset($mediaData['file'])) {
            return;
        }

        $archivedPath = $filesDir . '/' . $mediaData['file'];
        if (!is_file($archivedPath)) {
            return;
        }

        $name = $this->archivedName($mediaData['name'] ?? null);
        if (null === $name || !$this->isNameFree($name, $media)) {
            $media->setFile(new ReplacingFile($archivedPath, true, true, true));

            return;
        }

        $target = $this->projectDir . '/' . $root . '/' . $name;
        $this->filesystem->copy($archivedPath, $target, true);

        // Written by hand for the very reason the name was kept: nothing went through the upload pipeline, which is what sets the two columns of a media it stores
        $media
            ->setName($name)
            ->setSize(filesize($target) ?: null)
            ->setUpdatedAt(isset($mediaData['updatedAt']) ? new \DateTimeImmutable($mediaData['updatedAt']) : new \DateTimeImmutable());
    }

    // Whether the archived name can be written as is - the column is unique site-wide, so a name another media already holds (a product resliced under a different slug, an archive imported twice over a renamed catalogue) falls back on Vich naming the file itself rather than failing the whole import on a constraint
    private function isNameFree(string $name, Media $media): bool
    {
        if ($name === $media->getName()) {
            return true;
        }

        return null === $this->mediaRepository->findOneBy(['name' => $name]);
    }

    // The name an archive says a file was served under, or null for anything this bundle would refuse to write. What comes out of an archive is a path an admin uploaded, so it is only honoured under this bundle's own media directory, and only as a plain relative name: a "../" or an absolute path would have an import lay files anywhere the process can write, and a null byte would have PHP stop reading the name where C does
    private function archivedName(?string $name): ?string
    {
        if (null === $name || !str_starts_with($name, self::MEDIA_DIRECTORY)) {
            return null;
        }

        return !str_contains($name, "\0") && !\in_array('..', explode('/', $name), true) ? $name : null;
    }

    // The dates the two listeners stamped over on the flush above, put back as the archive holds them: what the "new" badge and the "newest first" listing of the shop are counted from (see ProductStateService)
    /**
     * @param list<array{0: Product|ProductItem, 1: \DateTime}> $creationDates
     */
    private function restoreCreationDates(array $creationDates): void
    {
        if ([] === $creationDates) {
            return;
        }

        foreach ($creationDates as [$entity, $creation]) {
            $entity->setCreation($creation);
        }

        $this->em->flush();
    }

    // The products an editor picked to go with this one, replaced whole - a slug this environment holds nowhere (a product left out of the selection that was exported) is skipped rather than created empty
    /**
     * @param array<string, Product> $imported
     */
    private function replaceRelatedProducts(Product $product, array $slugs, array $imported): void
    {
        foreach ($product->getRelatedProducts()->toArray() as $existingRelated) {
            $product->removeRelatedProduct($existingRelated);
        }

        foreach ($slugs as $slug) {
            $related = $imported[$slug] ?? $this->productRepository->findOneBySlug($slug);
            if (null !== $related) {
                $product->addRelatedProduct($related);
            }
        }
    }
}
