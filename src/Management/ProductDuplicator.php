<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media as BlockMedia;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Copies a whole product - its pictures, its items with their picture and file, its categories and the blocks composing its sheet - the files copied on the disk rather than handed back to Vich, whose storage would move the original's away and re-run the resizing
class ProductDuplicator
{
    // What the columns hold, the suffix being appended within that budget rather than overflowing it
    private const int TITLE_LENGTH = 100;
    private const int SLUG_LENGTH = 100;

    private readonly Filesystem $filesystem;

    private readonly string $projectDir;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly SluggerInterface $slugger,
        private readonly TranslatorInterface $translator,
        ParameterBagInterface $parameterBag,
    ) {
        $this->filesystem = new Filesystem();
        $this->projectDir = (string) $parameterBag->get('kernel.project_dir');
    }

    // Persists the copy and returns it, as a draft - the position, the dates and the author are left to the listeners, which fill them on the flush as they do for a product created by hand
    public function duplicate(Product $product): Product
    {
        $suffix = ' ' . $this->translator->trans('label.copy_suffix', [], 'shop');
        $title = mb_substr((string) $product->getTitle(), 0, self::TITLE_LENGTH - mb_strlen($suffix)) . $suffix;

        $copy = new Product()
            ->setTitle($title)
            ->setSlug($this->uniqueSlug($title))
            ->setDescription((string) $product->getDescription())
            ->setBrand($product->getBrand())
            ->setAvailableAt($product->getAvailableAt())
            // The wording printed on the card and the way it is revealed: they belong to the offer, not to the one product that carried it
            ->setGiftCardText($product->getGiftCardText())
            ->setGiftCardScratch($product->hasGiftCardScratch())
            // A copy is a draft, whatever the original was: it carries its title and its prices, and nothing else says it is meant to be sold as it stands
            ->setIsPublished(false)
        ;

        // The very same categories, which are shared rather than owned by the product
        foreach ($product->getCategories() as $category) {
            $copy->addCategory($category);
        }

        // The same picks, pointing at the same products: what goes with the original goes with a copy of it, and the relation is one-way, so nothing is written on the products aimed at
        foreach ($product->getRelatedProducts() as $related) {
            $copy->addRelatedProduct($related);
        }

        foreach ($product->getMedias() as $media) {
            $this->duplicateMedia($media, $copy);
        }

        foreach ($product->getItems() as $item) {
            $this->duplicateItem($item, $copy);
        }

        foreach ($product->getBlocks() as $block) {
            $copy->addBlock($this->duplicateBlock($block, (string) $product->getSlug(), (string) $copy->getSlug()));
        }

        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        return $copy;
    }

    // #[UniqueEntity] only guards the form, so the "-2, -3..." suffixing is what keeps a copy of a copy from colliding here
    private function uniqueSlug(string $title): string
    {
        return UniqueSlug::build(
            $this->slugger,
            mb_substr($title, 0, self::SLUG_LENGTH - 10),
            fn (string $candidate): bool => null !== $this->productRepository->findOneBy(['slug' => $candidate]),
        );
    }

    private function duplicateMedia(ProductMedia $media, Product $copy): void
    {
        $mediaCopy = new ProductMedia()
            ->setAlt($media->getAlt())
            ->setPosition($media->getPosition())
            ->setSize($media->getSize())
            ->setUpdatedAt(new \DateTimeImmutable())
        ;

        // Attached first: the path the file is copied to is read through the product the media hangs from
        $copy->addMedia($mediaCopy);
        $mediaCopy->setName($this->copyFile($media->getName(), $mediaCopy->getVichMediaPath(), 'public'));
    }

    private function duplicateItem(ProductItem $item, Product $copy): void
    {
        $itemCopy = new ProductItem()
            ->setTitle((string) $item->getTitle())
            ->setSlug((string) $item->getSlug())
            ->setDescription((string) $item->getDescription())
            ->setPrice((int) $item->getPrice())
            ->setPriceBefore($item->getPriceBefore())
            ->setCurrency((string) $item->getCurrency())
            // The reference and the barcode are deliberately left behind: they name one item and one only, and two offers publishing the same GTIN is exactly the claim the column exists to avoid
            ->setSku(null)
            ->setGtin(null)
            ->setVat((float) $item->getVat())
            ->setLimitedQuantity($item->getLimitedQuantity())
            // Nothing has been sold of the copy, and the limited quantity is counted against this
            ->setOrderedQuantity(null)
            ->setService($item->isService())
            // Carried like the price: a copy of a gift card is a gift card, and without this value Product::isGiftCard() answers no for the whole copy
            ->setGiftCardValue($item->getGiftCardValue())
            // Carried as it stands: the copy is a draft as a whole, so an item left offline in the original has no reason to come back on sale in it
            ->setIsPublished($item->isPublished())
            ->setItemCondition($item->getItemCondition())
            ->setPosition($item->getPosition())
        ;

        // Attached first, as both the picture and the file walk back to the product through the item
        $copy->addItem($itemCopy);

        $media = $item->getMedia();
        if (null !== $media) {
            $mediaCopy = new ProductItemMedia()
                ->setAlt($media->getAlt())
                ->setPosition($media->getPosition())
                ->setSize($media->getSize())
                ->setUpdatedAt(new \DateTimeImmutable())
            ;
            $itemCopy->setMedia($mediaCopy);
            $mediaCopy->setName($this->copyFile($media->getName(), $mediaCopy->getVichMediaPath(), 'public'));
        }

        $file = $item->getFile();
        if (null !== $file) {
            $fileCopy = new ProductItemFile()
                ->setPosition($file->getPosition())
                ->setSize($file->getSize())
                ->setUpdatedAt(new \DateTimeImmutable())
            ;
            $itemCopy->setFile($fileCopy);
            $fileCopy->setName($this->copyFile($file->getName(), $fileCopy->getVichMediaPath(), $fileCopy->getPrivateDirectory()));
        }
    }

    // A block of the sheet, its own medias and, for a container kind, the blocks sitting in its slots
    private function duplicateBlock(Block $block, string $slug, string $copySlug): Block
    {
        $blockCopy = new Block()
            ->setKind($block->getKind())
            ->setPosition($block->getPosition())
            ->setAnimation($block->getAnimation())
            ->setData($this->remapData($block->getData(), $slug, $copySlug))
        ;

        foreach ($block->getMedias() as $media) {
            $this->duplicateBlockMedia($media, $blockCopy);
        }

        foreach ($block->getSlots() as $slot) {
            $blockCopy->addSlot($this->duplicateBlock($slot, $slug, $copySlug));
        }

        return $blockCopy;
    }

    // A "shop_product_items" or "shop_product_slider" block naming the product it sits on sells the copy on the copy's sheet - one naming another product goes on naming it
    private function remapData(array $data, string $slug, string $copySlug): array
    {
        if (($data['productSlug'] ?? null) === $slug) {
            $data['productSlug'] = $copySlug;
        }

        return $data;
    }

    private function duplicateBlockMedia(BlockMedia $media, Block $blockCopy): void
    {
        $mediaCopy = new BlockMedia()
            ->setPosition($media->getPosition())
            ->setSize($media->getSize())
            ->setMimeType($media->getMimeType())
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setAlt($media->getAlt())
            ->setLabel($media->getLabel())
            ->setName($media->getName())
            ->setWidth($media->getWidth())
            ->setHeight($media->getHeight())
            ->setCssClasses($media->getCssClasses())
            ->setAbove($media->isAbove())
            ->setCredits($media->getCredits())
            ->setRightsReserved($media->isRightsReserved())
            ->setUrl($media->getUrl())
            ->setDescription($media->getDescription())
        ;

        $blockCopy->addMedia($mediaCopy);
        $mediaCopy->setFilename($this->copyFile($media->getFilename(), $this->blockMediaBasePath($mediaCopy), 'public'));
    }

    // Where a fresh upload on the copy would have landed: the admin-typed name when there is one, the block's own path otherwise (see UiBundle's UiMediaNamer)
    private function blockMediaBasePath(BlockMedia $media): string
    {
        $name = trim((string) $media->getName());
        if ('' === $name) {
            return $media->getVichMediaPath();
        }

        $directory = dirname($media->getVichMediaPath());

        return ('.' !== $directory ? $directory . '/' : '') . strtolower($this->slugger->slug($name)->toString());
    }

    // Copies the file next to the original under a name of its own and returns it, null when the media carries no file yet or when the file is gone from the disk
    private function copyFile(?string $name, string $basePath, string $directory): ?string
    {
        if (null === $name || '' === $name) {
            return null;
        }

        $source = $this->projectDir . '/' . $directory . '/' . $name;
        if (!$this->filesystem->exists($source)) {
            return null;
        }

        $extension = strtolower(pathinfo($name, \PATHINFO_EXTENSION));
        $copyName = $basePath . '-' . uniqid() . ('' !== $extension ? '.' . $extension : '');
        $target = $this->projectDir . '/' . $directory . '/' . $copyName;
        $this->filesystem->copy($source, $target);

        // A public pdf is shown through the webp of its first page, drawn once on upload by Ghostscript - which is not available on every host, so it is carried over rather than redrawn
        if ('pdf' === $extension && $this->filesystem->exists(VichPdfThumbnailListener::toWebpPath($source))) {
            $this->filesystem->copy(VichPdfThumbnailListener::toWebpPath($source), VichPdfThumbnailListener::toWebpPath($target));
        }

        return $copyName;
    }
}
