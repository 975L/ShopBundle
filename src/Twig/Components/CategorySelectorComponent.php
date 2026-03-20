<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig\Components;

use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'c975LShop:Product:CategoriesSelect',
    template: '@c975LShop/components/Product/CategoriesSelect.html.twig'
)]
class CategorySelectorComponent extends AbstractController
{
    use DefaultActionTrait;

    public array $categories = [];

    #[LiveProp(writable: true)]
    public ?string $selectedCategory = null;

    public function __construct(
        private readonly ProductCategoryServiceInterface $productCategoryService,
    ) {
    }

    public function mount(array $categories = []): void
    {
        $this->categories = $this->productCategoryService->findAll();
    }

    #[LiveAction]
    public function changeCategory(): Response
    {
        if ($this->selectedCategory === 'all') {
            return $this->redirectToRoute('shop_index');
        }

        if ($this->selectedCategory) {
            return $this->redirectToRoute('category_display', [
                'slug' => $this->selectedCategory
            ]);
        }

        return new Response();
    }
}