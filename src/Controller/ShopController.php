<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\ShopBundle\Service\ShopServiceInterface;
use c975L\ShopBundle\Service\ShopTranslatedLocales;
use c975L\ShopBundle\Service\ShopTranslator;
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
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly ShopTranslatedLocales $translatedLocales,
        private readonly ShopTranslator $shopTranslator,
    ) {
    }

    // INDEX - the same index in another language, the writing language keeping "/shop" byte for byte: the pattern holds the languages the site declares beside the one it is written in and matches nothing while there are none (see c975LConfigBundle::declareLocalesPattern), so a single-language shop only ever answers on the bare route
    #[Route(
        '/{_locale}/shop',
        name: 'shop_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET']
    )]
    #[Route(
        '/shop',
        name: 'shop_index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forShop(), 'shop_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        // Read once for the two things the index takes from it, the row being absent on a shop that never opened the back-office screen
        $settings = $this->shopSettingsRepository->findSingle();

        $products = $this->shopService->findAllProductsPaginated($request->query);

        // The language being read laid over the names and descriptions, for this render and no longer: called here rather than on postLoad, the back office having to go on showing the text a row was written in (see ShopTranslator::apply)
        $this->shopTranslator->apply($products);
        $this->shopTranslator->apply(null === $settings ? [] : [$settings]);

        return $this->negotiator->vary($request, $this->render(
            '@c975LShop/shop/index.html.twig',
            [
                'products' => $products,
                'categoriesCount' => $this->shopService->countCategories(),
                'order' => $this->shopService->getOrder($request->query),
                'filters' => $this->shopService->getFilters($request->query),
                'priceBrackets' => $this->shopService->getPriceBrackets(),
                // The shop's own line - null until the editor writes one, the template then falling back to the sentence the back-office menu describes the shop link with
                'shopIntro' => $settings?->getIntro(),
                // What the editor composed above the listing - an empty collection on a shop that never opened the screen, which renders nothing rather than failing on a row that was never created
                'shopBlocks' => $settings?->getBlocks() ?? [],
            ]
        ));
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
