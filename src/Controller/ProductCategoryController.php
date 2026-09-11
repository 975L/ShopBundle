<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ShopTranslatedLocales;
use c975L\ShopBundle\Service\ShopTranslator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductCategoryController extends AbstractController
{
    public function __construct(
        private readonly ProductServiceInterface $productService,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly ShopTranslatedLocales $translatedLocales,
        private readonly ShopTranslator $shopTranslator,
    ) {
    }

    // DISPLAY - the same category, in another language, a route that matches and answers 404 for as long as a category is written in one language alone (see ShopTranslatedLocales::forCategory)
    #[Route(
        '/{_locale}/shop/category/{slug}',
        name: 'category_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-zA-Z0-9\-]*)',
        ],
        methods: ['GET']
    )]
    #[Route(
        '/shop/category/{slug}',
        name: 'category_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET']
    )]
    public function display(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        ProductCategory $category,
        Request $request,
    ): Response {
        $locales = $this->translatedLocales->forCategory($category);

        // A localised url answers for every language the site declares: the guard stays as the one place that would refuse one, and refuses nothing while these screens are read in all of them (see ShopTranslatedLocales)
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'category_display', ['slug' => $category->getSlug()]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        // Read through the repository rather than off the association: a category holds its hidden and its trashed products too, which the page would otherwise card up and link to a 404
        $products = $this->productService->findByCategorySlug($category->getSlug());

        // The category and its cards in the language being read (see ShopTranslator::apply)
        $this->shopTranslator->apply([$category, ...$products]);

        return $this->negotiator->vary($request, $this->render(
            '@c975LShop/category/display.html.twig',
            [
                'category' => $category,
                'products' => $products,
            ]
        ));
    }
}
