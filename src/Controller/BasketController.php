<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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

    // GETS BASKET JSON
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
        methods: ['GET']
    )]
    public function display()
    {
        $basket = $this->basketService->get();

        //Renders the page
        return $this->render('@c975LShop/basket/display.html.twig', [
            'action' => 'display',
            'basket' => $basket,
        ]);
    }

    // VALIDATE
    #[Route(
        '/shop/basket/validate',
        name: 'basket_validate',
        methods: ['GET', 'POST']
    )]
    public function validate(Request $request): Response
    {
        $basket = $this->basketService->get();

        if (null === $basket) {
            return $this->redirectToRoute('basket_display', [], Response::HTTP_SEE_OTHER);
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
            'action' => 'validate',
            'form' => $form->createView(),
            'basket' => $basket,
        ]);
    }

    // PAID
    #[Route(
        '/shop/basket/paid/{number:basket}/{securityToken:basket}',
        name: 'basket_paid',
        requirements: [
            'number' => '.{15,20}',
            'securityToken' => '[a-f0-9]{16}'
        ],
        methods: ['GET']
    )]
    public function paid(?Basket $basket): Response
    {
        if (null !== $basket) {
            $this->basketService->paid($basket);
        }

        return $this->render('@c975LShop/basket/display.html.twig', [
            'action' => 'paid',
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
        return new JsonResponse($this->basketService->addItem($request));
    }

    // DELETE PRODUCT ITEM
    #[Route(
        '/shop/basket/delete',
        name: 'basket_product_delete',
        methods: ['DELETE']
    )]
    public function remove(Request $request): JsonResponse
    {
        return new JsonResponse($this->basketService->deleteItem($request));
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

    // ITEMS SHIPPED
    #[Route(
        '/shop/basket/items-shipped/{number}/{type}',
        name: 'items_shipped',
        requirements: [
            'number' => '.{15,20}',
            'type' => 'product|crowdfunding'
        ],
        methods: ['GET']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function itemsShipped(string $number, string $type): Response
    {
        $basket = $this->basketService->itemsShipped($number, $type);

        return $this->render(
            '@c975LShop/basket/shipped.html.twig',
            [
                'basket' => $basket,
            ]
        )->setMaxAge(3600);
    }
}
