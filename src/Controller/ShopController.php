<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Service\ShopServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
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