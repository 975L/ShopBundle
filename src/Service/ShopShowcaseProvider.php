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
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Shows this bundle's nine block kinds in a block showcase (see UiBundle's GalleryShowcaseRegistry). None of them fits BlockFixtureProviderInterface: their templates resolve real content live via shop_block_*() (ShopBlockExtension), querying the catalog straight from the database. Rendered here instead, directly against the same components, bypassing those queries - the very split GalleryBundle makes
class ShopShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        private readonly BlockFixtureMediaAttacher $mediaAttacher,
        private readonly ShopSampleCatalog $catalog,
    ) {
    }

    public function getShowcases(): array
    {
        // A products grid with no image in it shows nothing worth looking at, so a site declaring no placeholder image gets no shop showcase rather than a row of broken frames
        $images = $this->placeholderMediaRegistry->getImages();
        if ([] === $images) {
            return [];
        }

        $products = $this->products($images, 3);

        return [
            $this->label('label.block_products') => [
                'description' => $this->label('label.block_products_description'),
                'kind' => 'shop_products',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Products.html.twig', ['products' => $products])],
            ],
            $this->label('label.block_gift_cards') => [
                'description' => $this->label('label.block_gift_cards_description'),
                'kind' => 'shop_gift_cards',
                // PaymentBundle's own component, that bundle owning the card this one only sells - handed no amount and no code, which is exactly what the block shows of a card nobody has bought yet
                'variants' => ['' => '<div class="cards">' . $this->render('@c975LPayment/components/GiftCard/Card.html.twig', [
                    'image' => $images[0],
                    'text' => $this->translator->trans('label.shop_showcase_gift_card_text', [], 'shop'),
                    'scratch' => true,
                ]) . '</div>'],
            ],
            $this->label('label.block_categories') => [
                'description' => $this->label('label.block_categories_description'),
                'kind' => 'shop_categories',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Categories.html.twig', ['categories' => $this->categories(3)])],
            ],
            $this->label('label.block_product_card') => [
                'description' => $this->label('label.block_product_card_description'),
                'kind' => 'shop_product',
                'variants' => ['' => '<div class="products">' . $this->render('@c975LShop/components/Product/Product.html.twig', ['product' => $products[0]]) . '</div>'],
            ],
            $this->label('label.block_product_button') => [
                'description' => $this->label('label.block_product_button_description'),
                'kind' => 'shop_product_button',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Button.html.twig', ['product' => $products[0], 'type' => 'primary'])],
            ],
            $this->label('label.block_recommendations') => [
                'description' => $this->label('label.block_recommendations_description'),
                'kind' => 'shop_recommendations',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Recommendations.html.twig', ['recommendations' => $products])],
            ],
            $this->label('label.block_product_items') => [
                'description' => $this->label('label.block_product_items_description'),
                'kind' => 'shop_product_items',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Items.html.twig', ['items' => $this->items($images)])],
            ],
            // Real Media entities rather than the plain arrays above: UiBundle's slider reads its images with vich_uploader_asset(), which needs the entity BlockFixtureMediaAttacher builds from a placeholder path - the very reuse its placeholderImagesFor() and nextPlaceholderImage() are public for
            $this->label('label.block_product_slider') => [
                'description' => $this->label('label.block_product_slider_description'),
                'kind' => 'shop_product_slider',
                'variants' => ['' => $this->render('@c975LUi/components/Slider/Slider.html.twig', ['media' => $this->sliderMedias(3), 'id' => 'showcase-product-slider', 'class' => 'img-500', 'duration' => 3500])],
            ],
            // The live search renders its input and nothing else as long as nothing is typed, so it previews as it looks on a page - only its cache entry was ever the problem, not its render
            $this->label('label.block_search') => [
                'description' => $this->label('label.block_search_description'),
                'kind' => 'shop_search',
                'variants' => ['' => $this->render('@c975LShop/components/Product/Search.html.twig', ['categorySlug' => null])],
            ],
        ];
    }

    // Stand-ins are real Product entities, never persisted: the components resolve their badge, price and formats through shop_product_state(), typed on Product, where an array only gets as far as a TypeError. Carrying no id costs nothing here - shop_product_edit_url() answers null on an in-memory one, the cards keep their own path, and the one thing filed under an id, the favorite heart, is left out of a card standing for no stored product (see Product.html.twig)
    /**
     * @param list<string> $images
     *
     * @return list<Product>
     */
    private function products(array $images, int $count): array
    {
        $specs = \array_slice($this->catalog->getProducts(), 0, $count);

        $products = [];
        foreach (\array_slice($images, 0, \count($specs)) as $index => $image) {
            $spec = $specs[$index];

            $product = new Product();
            $product->setTitle($this->label($spec['title']));
            $product->setSlug($spec['slug']);
            $product->addMedia($this->productMedia($image));
            // Its first item only, so the card shows a real price and format rather than a title over an empty footer
            $product->addItem($this->item($spec['items'][0], $image));

            $products[] = $product;
        }

        return $products;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(int $count): array
    {
        $categories = [];
        foreach (\array_slice($this->catalog->getCategories(), 0, $count, true) as $slug => $nameKey) {
            $categories[] = [
                'slug' => $slug,
                'name' => $this->label($nameKey),
            ];
        }

        return $categories;
    }

    // One physical item and one digital one, the two the icon and the type line of a real sheet tell apart
    /**
     * @param list<string> $images
     *
     * @return list<ProductItem>
     */
    private function items(array $images): array
    {
        $image = $images[0];

        $specs = [];
        foreach ($this->catalog->getProducts() as $product) {
            foreach ($product['items'] as $item) {
                // The first of each kind and nothing more, two lines being what tells the icon and the type line apart
                $specs[null === $item['file'] ? 'posted' : 'downloaded'] ??= $item;
            }
        }

        return array_values(array_map(fn (array $spec): ProductItem => $this->item($spec, $image), $specs));
    }

    // A file name and nothing else tells a downloaded item from a posted one, the same way getItemFormat() reads it. The stock is left unlimited on purpose: the column defaults to 0, which is what an item withdrawn from sale says, and every button of the showcase would render disabled
    /**
     * @param array<string, mixed> $spec
     */
    private function item(array $spec, string $image): ProductItem
    {
        $item = new ProductItem();
        $item->setSlug($spec['slug']);
        $item->setTitle($this->label($spec['title']));
        $item->setDescription($this->label($spec['description']));
        $item->setPrice($spec['price']);
        $item->setPriceBefore($spec['priceBefore']);
        $item->setCurrency('EUR');
        $item->setLimitedQuantity(null);
        $item->setOrderedQuantity(0);
        $item->setService($spec['service']);

        $media = new ProductItemMedia();
        $media->setName($image);
        $item->setMedia($media);

        if (null !== $spec['file']) {
            $file = new ProductItemFile();
            $file->setName($spec['file']);
            $item->setFile($file);
        }

        return $item;
    }

    // The picture of a product, read by the card through Media::__toString() - which answers the very placeholder path the site declared
    private function productMedia(string $image): ProductMedia
    {
        $media = new ProductMedia();
        $media->setName($image);

        return $media;
    }

    // The photographs of one single product, which is what a product slider leafs through - the generic pool only shows unrelated landscapes side by side, saying nothing about the block. Keyed "shop/<slug>" by the site, exactly as the demo dataset reads them (see ShopDemoFixtureProvider), and falling back on that pool for a site declaring none rather than previewing an empty slider
    /**
     * @return list<Media>
     */
    private function sliderMedias(int $count): array
    {
        $spec = $this->catalog->getProducts()[0];
        $medias = $this->mediaAttacher->placeholderImagesFor('shop/' . $spec['slug'], $this->label($spec['title']));

        return [] === $medias ? $this->placeholderMedias($count) : \array_slice($medias, 0, $count);
    }

    /**
     * @return list<Media>
     */
    private function placeholderMedias(int $count): array
    {
        $medias = [];
        for ($i = 0; $i < $count; ++$i) {
            $media = $this->mediaAttacher->nextPlaceholderImage();
            if (null === $media) {
                break;
            }
            $medias[] = $media;
        }

        return $medias;
    }

    private function label(string $key): string
    {
        return $this->translator->trans($key, [], 'shop');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        return $this->twig->render($template, $context);
    }
}
