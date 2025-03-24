<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use c975L\ShopBundle\Service\BasketServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
class BasketController extends AbstractController
{
    public function __construct(
        private readonly BasketServiceInterface $basketService
    ) {
    }

    // GETS BASKET
    #[Route(
        '/shop/basket/json',
        name: 'basket_json',
        methods: ['GET']
    )]
    public function getJson(): JsonResponse
    {
        return new JsonResponse($this->basketService->getJson());
    }

    // DISPLAY
    #[Route(
        '/shop/basket/display',
        name: 'basket_display',
        methods: ['GET', 'POST']
    )]
    public function display(Request $request)
    {
        $basket = $this->basketService->get();

        // Empty basket
        if (null === $basket) {
            return $this->render('@c975LShop/basket/empty.html.twig');
        }

        //Defines form
        $form = $this->basketService->createForm('coordinates', $basket);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $url = $this->basketService->validate($request);
            if (null !== $url) {
                return $this->redirect($url, Response::HTTP_SEE_OTHER);
            }
        }

        //Renders the page
        return $this->render('@c975LShop/basket/display.html.twig', [
            'form' => $form->createView(),
            'basket' => $basket,
        ]);
    }

    // DELETE
    #[Route(
        '/shop/basket',
        name: 'basket_delete',
        methods: ['DELETE']
    )]
    public function delete(): JsonResponse
    {
        return new JsonResponse($this->basketService->delete());
    }

    // VALIDATED
    #[Route(
        '/shop/basket/validated/{number:basket}',
        name: 'basket_validated',
        requirements: ['number' => '.{10,20}'],
        defaults: ['number' => ''],
        methods: ['GET']
    )]
    public function validated(?Basket $basket): Response
    {
        if (null === $basket) {
            $basket = $this->basketService->validated();

            if (null !== $basket) {
                return $this->redirectToRoute('basket_validated', ['number' => $basket->getNumber()]);
            }
        }

        return $this->render('@c975LShop/basket/validated.html.twig', [
            'basket' => $basket,
        ]);
    }

    // ADD PRODUCT ITEM
    #[Route(
        '/shop/basket',
        name: 'basket_add',
        methods: ['POST']
    )]
    public function add(Request $request): JsonResponse
    {
        return new JsonResponse($this->basketService->addProductItem($request));
    }

    // DELETE PRODUCT ITEM
    #[Route(
        '/shop/basket/delete',
        name: 'basket_product_delete',
        methods: ['DELETE']
    )]
    public function remove(Request $request): JsonResponse
    {
        return new JsonResponse($this->basketService->deleteProductItem($request));
    }
}
