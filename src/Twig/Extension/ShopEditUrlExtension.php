<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Twig\Extension;

use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

// Points the public pages' "edit" button at what is being looked at - the product a card stands for, the category a listing is filed under, the field a section is printed from - the admin url generated rather than written out, the role deciding who is offered it being checked by the templates, where it costs no query
class ShopEditUrlExtension
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    // A field name opens that field's own tab, scrolls to it and focuses it (see UiBundle's field-focus.js, mounted on every admin page): the sheet's gallery leads to "medias", its items list to "items", and the product itself to the top of its form
    #[AsTwigFunction('shop_product_edit_url')]
    public function getProductEditUrl(Product $product, ?string $focusField = null): ?string
    {
        return $this->editUrl(ProductCrudController::class, $product->getId(), $focusField);
    }

    // A category page holds nothing of its own but its description, its cards standing for the products they list (see components/Product/Product.html.twig)
    #[AsTwigFunction('shop_product_category_edit_url')]
    public function getProductCategoryEditUrl(ProductCategory $category, ?string $focusField = null): ?string
    {
        return $this->editUrl(ProductCategoryCrudController::class, $category->getId(), $focusField);
    }

    private function editUrl(string $crudControllerFqcn, ?int $entityId, ?string $focusField): ?string
    {
        // An entity with no id has no screen to point at - an in-memory one, a fixture preview
        if (null === $entityId) {
            return null;
        }

        $adminUrl = $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudControllerFqcn)
            ->setAction(Action::EDIT)
            ->setEntityId($entityId)
        ;

        if (null !== $focusField) {
            $adminUrl->set('focusField', $focusField);
        }

        // Null when the URL can't be built at all: EasyAdmin resolves the dashboard it is mounted under through a cache map written only when the route collection is regenerated (see AdminRouteGenerator::saveAdminRoutesInCache()), so that pool being emptied while the compiled routes stay fresh makes every generateUrl() call from a public page throw, and it stays that way until the routes are regenerated. The button is an editor-only convenience - losing it beats taking the page down for the only people able to fix it
        try {
            return $adminUrl->generateUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}
