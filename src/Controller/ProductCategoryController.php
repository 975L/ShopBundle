<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\ProductCategory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductCategoryController extends AbstractController
{
    public function __construct(private readonly ProductCategoryServiceInterface $productCategoryService)
    {
    }

    // DISPLAY
    #[Route(
        '/shop/category/{slug}',
        name: 'category_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET']
    )]
    public function display(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        ProductCategory $category
    ): Response
    {
        return $this->render(
            '@c975LShop/category/display.html.twig',
            [
                'category' => $category,
            ]
        )->setMaxAge(3600);
    }
}
