<?php

namespace c975L\ShopBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use c975L\ShopBundle\Service\BasketServiceInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    // ADD
    #[Route(
        '/basket',
        name: 'basket_add',
        methods: ['POST']
    )]
    public function add(Request $request): JsonResponse
    {
        return new JsonResponse($this->basketService->add($request));
    }

    // GETS TOTAL AND QUANTITY
    #[Route(
        '/basket/total',
        name: 'basket_total',
        methods: ['GET']
    )]
    public function total(): JsonResponse
    {
        return new JsonResponse($this->basketService->getTotal());
    }

    // DISPLAY
    #[Route(
        '/basket',
        name: 'basket_display',
        methods: ['GET', 'POST']
    )]
    public function display(Request $request): Response
    {
        $basket = $this->basketService->get();

        //Defines form
        $form = $this->basketService->createForm('coordinates', $basket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dd($form->getData());
            $redirectUrl = $this->contactFormService->sendEmail($form);
            if (null !== $redirectUrl) {
                return $this->redirect($redirectUrl);
            }
        }

        //Renders the form
        return $this->render('@c975LShop/basket/display.html.twig', [
            'form' => $form->createView(),
            'basket' => $basket,
        ]);
    }

    // DELETE
    #[Route(
        '/basket',
        name: 'basket_delete',
        methods: ['DELETE']
    )]
    public function delete(): JsonResponse
    {
        return new JsonResponse($this->basketService->delete());
    }

        // DELETE PRODUCT
        #[Route(
            '/basket/delete',
            name: 'basket_product_delete',
            methods: ['DELETE']
        )]
        public function remove(Request $request): JsonResponse
        {
            return new JsonResponse($this->basketService->deleteProduct($request));
        }

    // VALIDATE
    #[Route(
        '/basket/validate',
        name: 'basket_validate',
        methods: ['POST']
    )]
    public function validate(Request $request): JsonResponse
    {
        return new JsonResponse($this->basketService->validate($request));
    }
}
