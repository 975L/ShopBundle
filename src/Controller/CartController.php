<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Cart;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function add(Request $request): RedirectResponse
    {
        $this->cartService->add($request);

        $this->addFlash(
            'success',
            'Produit ajouté au panier avec succès.'
        );

        return $this->redirectToRoute('product_display', [
            'slug' => $request->request->get('slug')
        ]);
    }

    // REMOVE
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

    // VALIDATE
    #[Route(
        '/cart/validate',
        name: 'cart_validate',
        methods: ['GET']
    )]
    public function validate(): Response
    {
dd();
        return $this->render(
            '@c975LShop/cart/validate.html.twig',
            [
                'cart' => $this->cartService->get(),
            ]
        );
    }
}
