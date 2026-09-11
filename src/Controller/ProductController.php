<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductRecommendationServiceInterface;
use c975L\ShopBundle\Service\ShopTranslatedLocales;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Service\BlockRenderContext;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRecommendationServiceInterface $recommendationService,
        private readonly ConfigServiceInterface $configService,
        private readonly BlockRenderContext $blockRenderContext,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly ShopTranslatedLocales $translatedLocales,
        private readonly ShopTranslator $shopTranslator,
    ) {
    }

    // DISPLAY - the same product, in another language, a route that matches and answers 404 for as long as a product is written in one language alone (see ShopTranslatedLocales::forProduct)
    #[Route(
        '/{_locale}/shop/products/{slug}',
        name: 'product_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-zA-Z0-9\-]*)',
        ],
        methods: ['GET']
    )]
    #[Route(
        '/shop/products/{slug}',
        name: 'product_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET']
    )]
    public function display(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Product $product,
        Request $request,
    ): Response {
        // A trashed product is gone rather than missing, and a search engine acts on a 410 far faster than on the 404 the same url would otherwise answer - for as long as the product can still be restored, a Redirect taking over once it is deleted for good (see ProductCrudController::deletePermanently())
        if ($product->isDeleted()) {
            throw new GoneHttpException();
        }

        // A hidden product has nothing to say beyond that this url leads nowhere for now - 404 and not the 410 of the recycle bin, nothing having been taken away
        if ($product->isHidden()) {
            throw $this->createNotFoundException();
        }

        $locales = $this->translatedLocales->forProduct($product);

        // A localised url answers for every language the site declares: the guard stays as the one place that would refuse one, and refuses nothing while these screens are read in all of them (see ShopTranslatedLocales)
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'product_display', ['slug' => $product->getSlug()]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $similarProducts = $this->recommendationService->getSimilarProducts($product, 4);

        // The sheet, its variants and the cards under it, in the language being read (see ShopTranslator::apply)
        $this->shopTranslator->apply([$product, ...$similarProducts]);
        $this->shopTranslator->apply($product->getItems());

        return $this->negotiator->vary($request, $this->render('@c975LShop/product/display.html.twig', [
            'product' => $product,
            'similarProducts' => $similarProducts,
        ]));
    }

    // PREVIEW
    #[Route(
        '/shop/products/{slug}/preview',
        name: 'product_preview',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET'],
        priority: 1
    )]
    public function preview(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Product $product,
    ): Response {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        // A preview shows what was just saved, and its html is not the public one - said before anything is rendered, the sheet being composed of cacheable blocks
        $this->blockRenderContext->disableCache();

        if ($product->isDeleted()) {
            throw $this->createNotFoundException();
        }

        return $this->render('@c975LShop/product/display.html.twig', [
            'product' => $product,
            'similarProducts' => $this->recommendationService->getSimilarProducts($product, 4),
            'isPreview' => true,
        ])->setPrivate();
    }
}
