<?php

namespace c975L\ShopBundle\Twig\Components;

use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopTranslator;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'c975LShop:ProductSearch',
    template: '@c975LShop/components/ProductSearch.html.twig'
)]
class ProductSearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public ?string $categorySlug = null;

    public function __construct(
        private ProductServiceInterface $productService,
        private ShopTranslator $shopTranslator,
    ) {
    }

    public function getProducts(): ?array
    {
        if ('' === trim($this->query)) {
            return null;
        }

        // Read in the language being rendered, like every listing of the shop
        $products = $this->productService->search($this->query, $this->categorySlug);
        $this->shopTranslator->apply($products);

        return $products;
    }
}
