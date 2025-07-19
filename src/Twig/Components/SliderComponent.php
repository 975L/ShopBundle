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

#[AsTwigComponent('c975LShop:Slider:Slider', template: '@c975LShop/components/Slider/Slider.html.twig')]
final class SliderComponent
{
    public ?string $slug = null;
    private ?Product $product = null;
    private array $slides = [];

    public function __construct(
        private ProductRepository $productRepository
    ) {
    }

    public function getSlides(): array
    {
        $product = $this->getProduct();
        if ($product) {
            return $product->getMedias()->toArray();
        }

        return $this->slides;
    }

    private function getProduct(): ?Product
    {
        if ($this->product === null && $this->slug !== null) {
            $this->product = $this->productRepository->findOneBySlug($this->slug);
        }

        return $this->product;
    }

    public function mount(?string $slug = null, ?array $slides = null): void
    {
        if ($slug) {
            $this->slug = $slug;
        }

        if ($slides) {
            $this->slides = $slides;
        }
    }
}