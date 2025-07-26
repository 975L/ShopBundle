<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig\Components;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('c975LShop:Product:Card', template: '@c975LShop/components/Product/Product.html.twig')]
final class CardComponent
{
    public ?string $slug = null;
    public ?Product $product = null;

    public function __construct(
        private ProductRepository $productRepository
    ) {
    }

    private function getProduct(): ?Product
    {
        if ($this->product === null && $this->slug !== null) {
            $this->product = $this->productRepository->findOneBySlug($this->slug);
        }

        return $this->product;
    }

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $this->slug = $slug;
        }
        $this->product = $this->getProduct();
    }
}