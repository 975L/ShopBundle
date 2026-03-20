<?php

namespace c975L\ShopBundle\Twig\Components;

use c975L\ShopBundle\Service\ProductServiceInterface;
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
        private ProductServiceInterface $productService
    ) {
    }

    public function getProducts(): ?array
    {
        if (trim($this->query) === '') {
            return null;
        }

        return $this->productService->search($this->query, $this->categorySlug);
    }
}