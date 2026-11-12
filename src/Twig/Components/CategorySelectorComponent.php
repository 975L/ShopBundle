<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig\Components;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
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

    // The language of the page the selector was drawn on, the action itself being answered on the component's own url, which carries none
    #[LiveProp]
    public ?string $locale = null;

    public function __construct(
        private readonly ProductCategoryServiceInterface $productCategoryService,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The categories offered, and the language the visitor is reading the shop in
    public function mount(): void
    {
        $this->categories = $this->productCategoryService->findAllListed();
        $this->locale = $this->requestStack->getCurrentRequest()?->getLocale();
    }

    // Sends the visitor to the category picked, or to the whole shop
    #[LiveAction]
    public function changeCategory(): Response
    {
        // Sent to the page in the language it was chosen from, "/en/shop/category/x" rather than the writing language's url
        if ('all' === $this->selectedCategory) {
            return $this->redirect($this->localizedUrlGenerator->path('shop_index', [], null, $this->locale));
        }

        if ($this->selectedCategory) {
            return $this->redirect($this->localizedUrlGenerator->path('category_display', ['slug' => $this->selectedCategory], null, $this->locale));
        }

        return new Response();
    }
}
