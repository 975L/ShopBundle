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
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductRecommendationServiceInterface;
use c975L\UiBundle\Service\BlockRenderContext;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRecommendationServiceInterface $recommendationService,
        private readonly ConfigServiceInterface $configService,
        private readonly BlockRenderContext $blockRenderContext,
    ) {
    }

    // DISPLAY
    #[Route(
        '/shop/products/{slug}',
        name: 'product_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET']
    )]
    public function display(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Product $product,
    ): Response {
        // A trashed product is gone rather than missing, and a search engine acts on a 410 far faster than on the 404 the same url would otherwise answer - for as long as the product can still be restored, a Redirect taking over once it is deleted for good (see ProductCrudController::deletePermanently())
        if ($product->isDeleted()) {
            throw new GoneHttpException();
        }

        // A draft has never been online: nothing to say about it beyond that this url leads nowhere yet
        if (!$product->isPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render('@c975LShop/product/display.html.twig', [
            'product' => $product,
            'similarProducts' => $this->recommendationService->getSimilarProducts($product, 4),
        ]);
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
