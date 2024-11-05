<?php

namespace c975L\ShopBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use c975L\ShopBundle\Service\CartServiceInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartServiceInterface $cartService
    ) {
    }

    // ADD
    #[Route(
        '/cart',
        name: 'cart_add',
        methods: ['POST']
    )]
    public function add(Request $request): JsonResponse
    {
        return new JsonResponse($this->cartService->add($request));
    }

    // GETS TOTAL AND QUANTITY
    #[Route(
        '/cart/total',
        name: 'cart_total',
        methods: ['GET']
    )]
    public function total(): JsonResponse
    {
        return new JsonResponse($this->cartService->getTotal());
    }

    // DISPLAY
    #[Route(
        '/cart',
        name: 'cart_display',
        methods: ['GET']
    )]
    public function display(): Response
    {
        return $this->render(
            '@c975LShop/cart/display.html.twig',
            [
                'cart' => $this->cartService->get(),
            ]
        );
    }

    // DELETE
    #[Route(
        '/cart',
        name: 'cart_delete',
        methods: ['DELETE']
    )]
    public function delete(): JsonResponse
    {
        return new JsonResponse($this->cartService->delete());
    }

    // VALIDATE
    #[Route(
        '/cart/validate',
        name: 'cart_validate',
        methods: ['POST']
    )]
    public function validate(Request $request): JsonResponse
    {
        return new JsonResponse($this->cartService->validate($request));
    }




/* TODO */
    // REMOVE PRODUCT
    #[Route(
        '/cart',
        name: 'cart_remove',
        methods: ['POST']
    )]
    public function remove(Request $request): RedirectResponse
    {
dd();
        $this->cartService->remove($request);

        $this->addFlash(
            'success',
            'Produit retiré au panier avec succès.'
        );

        return $this->redirectToRoute('product_display', [
            'slug' => $request->request->get('slug')
        ]);
    }
}
