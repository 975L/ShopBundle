<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use c975L\ShopBundle\Service\ShopServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ShopController extends AbstractController
{
    public function __construct(private readonly ShopServiceInterface $shopService)
    {
    }

    // INDEX
    #[Route(
        '/shop',
        name: 'shop_index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LShop/shop/index.html.twig',
            ['products' => $this->shopService->findAllProductsPaginated($request->query)]
            )->setMaxAge(3600);
    }
}
