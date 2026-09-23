<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Hands SocialBundle's publication the shop's products, in the order the shop lists them - a site without SocialBundle simply never asks. What went out where is SocialBundle's to record
class ProductSocialContentSource implements SocialContentSourceInterface
{
    // A product is worth showing again a season later, a shop living on its regulars coming back
    private const int REPEAT_AFTER_DAYS = 90;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ShopPublicUrlResolver $shopPublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getSourceType(): string
    {
        return 'product';
    }

    public function getRepeatAfterDays(): ?int
    {
        return self::REPEAT_AFTER_DAYS;
    }

    // What the shop lists (neither hidden, trashed nor awaiting its availability date), taken in the shop's own order
    public function getNextContent(array $excludedIds): ?SocialContent
    {
        $products = $this->productRepository->findAvailableProductsExcluding(array_map(intval(...), $excludedIds));

        return [] === $products ? null : $this->toContent($products[0]);
    }

    // Null for a product taken off the shop since its post was prepared
    public function getContent(string $sourceId): ?SocialContent
    {
        $product = $this->productRepository->find((int) $sourceId);
        $availableAt = $product?->getAvailableAt();

        return $product instanceof Product && !$product->isHidden() && !$product->isDeleted() && (null === $availableAt || $availableAt <= new \DateTime())
            ? $this->toContent($product)
            : null;
    }

    // Null while "site-url" is unset: the run happens in a console, and a post linking to a relative url would lead nowhere
    private function toContent(Product $product): ?SocialContent
    {
        $url = $this->shopPublicUrlResolver->resolve('product_display', ['slug' => $product->getSlug()]);
        if (null === $url) {
            return null;
        }

        // The first photograph, the one the product's own page shares
        $image = $product->getMedias()->first() ?: null;
        $name = $image?->getName();

        return new SocialContent(
            sourceId: (string) $product->getId(),
            title: (string) $product->getTitle(),
            url: $url,
            imagePath: null === $name ? null : $this->projectDir . '/public/' . $name,
            imageUrl: null === $name ? null : $this->siteUrlResolver->siteUrl() . '/' . $name,
            imageAlt: $image?->getAlt() ?? (string) $product->getTitle(),
            variables: array_filter(['description' => trim(html_entity_decode(strip_tags((string) $product->getDescription())))]),
        );
    }
}
