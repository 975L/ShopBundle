<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\ShopBundle\Service\ShopServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    // Named rather than imported: SiteBundle is not a dependency of this one
    private const string SITE_BUNDLE = 'c975LSiteBundle';

    public function __construct(
        private readonly ShopServiceInterface $shopService,
        private readonly ShopSettingsRepository $shopSettingsRepository,
    ) {
    }

    // INDEX
    #[Route(
        '/shop',
        name: 'shop_index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        // Read once for the two things the index takes from it, the row being absent on a shop that never opened the back-office screen
        $settings = $this->shopSettingsRepository->findSingle();

        return $this->render(
            '@c975LShop/shop/index.html.twig',
            [
                'products' => $this->shopService->findAllProductsPaginated($request->query),
                'categoriesCount' => $this->shopService->countCategories(),
                'order' => $this->shopService->getOrder($request->query),
                'filters' => $this->shopService->getFilters($request->query),
                'priceBrackets' => $this->shopService->getPriceBrackets(),
                // The shop's own line - null until the editor writes one, the template then falling back to the sentence the back-office menu describes the shop link with
                'shopIntro' => $settings?->getIntro(),
                // What the editor composed above the listing - an empty collection on a shop that never opened the screen, which renders nothing rather than failing on a row that was never created
                'shopBlocks' => $settings?->getBlocks() ?? [],
            ]
        );
    }

    // TERMS OF SALES
    #[Route(
        '/shop/terms-of-sales',
        name: 'shop_terms_of_sales',
        methods: ['GET']
    )]
    public function termsOfSales(): Response
    {
        // Steps aside for the page SiteBundle's default import already creates: that one renders the model through its block, carrying whatever the client customized, this one renders it as the bundle ships it - a site serving both would publish two different contracts. Read on the registered bundles rather than with class_exists(): what matters is what the app booted, not what happens to sit in vendor/
        if (array_key_exists(self::SITE_BUNDLE, (array) $this->getParameter('kernel.bundles'))) {
            throw $this->createNotFoundException();
        }

        return $this->render('@c975LShop/shop/terms_of_sales.html.twig');
    }

    // REDIRECT
    #[Route(
        '/shop/management',
        name: 'shop_management_redirect',
        methods: ['GET']
    )]
    public function managementRedirect(): Response
    {
        return $this->redirectToRoute('management');
    }
}
