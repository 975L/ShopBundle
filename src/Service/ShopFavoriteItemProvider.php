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
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\FavoriteItemProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Turns the "shop_product" rows a wishlist holds back into cards - the only place of this bundle that knows what that name stands for, UiBundle storing a name and an id and nothing else
class ShopFavoriteItemProvider implements FavoriteItemProviderInterface
{
    // The vocabulary this bundle files its products under, the same one the rating widget and ProductCrudController already use
    public const string OWNER_TYPE = 'shop_product';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return self::OWNER_TYPE === $ownerType;
    }

    public function getItems(string $ownerType, array $ownerIds): array
    {
        $items = [];

        // The repository already leaves out what the visitor may no longer see, so a product taken offline simply stops showing on the lists it was on, and comes back on them the day it is published again
        foreach ($this->productRepository->findAvailableByIds($ownerIds) as $product) {
            $items[(int) $product->getId()] = new CollectionItem(
                title: trim((string) $product->getTitle()),
                description: $this->excerpt($product),
                imageUrl: $this->imageUrl($product),
                url: $this->urlGenerator->generate('product_display', ['slug' => $product->getSlug()]),
                slug: $product->getSlug(),
            );
        }

        return $items;
    }

    // The same hundred characters the product's own card shows under its title (see components/Product/Product.html.twig), the description being rich text where a card carries the words only
    private function excerpt(Product $product): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $product->getDescription()), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        if ('' === $text) {
            return null;
        }

        return mb_strlen($text) > 100 ? mb_substr($text, 0, 100) . '…' : $text;
    }

    // The picture the sheet opens on, which is the first of its gallery - a product carrying none shows a card with no image rather than a placeholder nobody chose
    private function imageUrl(Product $product): ?string
    {
        $media = $product->getMedias()->first();

        if (false === $media || null === $media->getName()) {
            return null;
        }

        return $this->packages->getUrl($media->getName());
    }
}
