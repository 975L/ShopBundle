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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The catalog a demo site is seeded with, persisted from the very data the block showcase renders in memory (see ShopSampleCatalog)
class ShopDemoFixtureProvider implements DemoFixtureProviderInterface
{
    public function __construct(
        private readonly ShopSampleCatalog $catalog,
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Only the categories and the products are yielded, their medias, items and files riding Product's ORM cascade so a reload takes the uploaded files off the disk with the rows
    public function getDemoFixtures(): iterable
    {
        $categories = [];
        $position = 0;

        foreach ($this->catalog->getCategories() as $slug => $nameKey) {
            $category = new ProductCategory();
            $category->setSlug($slug);
            $category->setName($this->trans($nameKey));
            $category->setPosition(++$position);

            $categories[$slug] = $category;

            yield $category;
        }

        $images = $this->placeholderMediaRegistry->getImages();
        $document = $this->placeholderMediaRegistry->getDocument();
        $position = 0;

        foreach ($this->catalog->getProducts() as $index => $spec) {
            yield $this->product($spec, $categories, $images, $document, $index, ++$position);
        }
    }

    /**
     * @param array<string, mixed>           $spec
     * @param array<string, ProductCategory> $categories
     * @param list<string>                   $images
     */
    private function product(array $spec, array $categories, array $images, ?string $document, int $index, int $position): Product
    {
        // Read off the catalog rather than the clock, so a reload puts the very same creation dates back
        $creation = new \DateTime($spec['creation']);

        $product = new Product();
        $product->setTitle($this->trans($spec['title']));
        $product->setSlug($spec['slug']);
        $product->setDescription($this->trans($spec['description']));
        $product->setPosition($position);
        $product->setIsPublished(true);
        $product->setCreation($creation);

        if (isset($categories[$spec['category']])) {
            $product->addCategory($categories[$spec['category']]);
        }

        // Rotated, a site rarely declaring as many placeholders as the catalog has products - declaring none still gets a shop, the card falling back on "no-product-image.webp"
        if ([] !== $images) {
            $file = $this->temporaryCopy($images[$index % \count($images)]);
            if (null !== $file) {
                $product->addMedia(new ProductMedia()->setFile($file));
            }
        }

        foreach ($spec['items'] as $itemSpec) {
            $product->addItem($this->item($itemSpec, $document, $creation));
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function item(array $spec, ?string $document, \DateTimeInterface $creation): ProductItem
    {
        $item = new ProductItem();
        $item->setSlug($spec['slug']);
        $item->setTitle($this->trans($spec['title']));
        $item->setDescription($this->trans($spec['description']));
        $item->setPrice($spec['price']);
        $item->setPriceBefore($spec['priceBefore']);
        $item->setCurrency('EUR');
        $item->setLimitedQuantity($spec['limitedQuantity']);
        $item->setOrderedQuantity(0);
        $item->setService($spec['service']);
        $item->setIsPublished(true);
        $item->setCreation($creation);

        // A site declaring no placeholder document leaves the item file-less, hence sold as a posted one rather than announced as downloadable with nothing to download
        if (null !== $spec['file'] && null !== $document) {
            $file = $this->temporaryCopy($document);
            if (null !== $file) {
                $item->setFile(new ProductItemFile()->setFile($file));
            }
        }

        return $item;
    }

    // VichUploader moves the file it is handed, so it gets a copy - as a ReplacingFile, a plain File being what UploadHandler::hasUploadedFile() ignores in silence
    private function temporaryCopy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;
        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        return copy($source, $target) ? new ReplacingFile($target, true, true, true) : null;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'shop');
    }
}
