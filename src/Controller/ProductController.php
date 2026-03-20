<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductRecommendationServiceInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRecommendationServiceInterface $recommendationService,
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
        Product $product
    ): Response
    {
        $similarProducts = $this->recommendationService->getSimilarProducts($product, 4);

        return $this->render('@c975LShop/product/display.html.twig', [
            'product' => $product,
            'similarProducts' => $similarProducts,
        ])->setMaxAge(3600);
    }
}
