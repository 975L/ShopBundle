<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\AiSearchCardProviderInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

// Draws a product the site search answered from as the shop's own card, with its price and, when it is sold as a single item, its basket button - the product urls told apart by the router itself, so a localized one is recognised like the others
class ShopAiSearchCardProvider implements AiSearchCardProviderInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ProductRepository $productRepository,
        private readonly Environment $twig,
    ) {
    }

    public function renderCards(array $urls): array
    {
        $slugs = [];
        foreach ($urls as $url) {
            $slug = $this->slug($url);
            if (null !== $slug) {
                $slugs[$slug] = $url;
            }
        }

        $cards = [];
        foreach ($this->productRepository->findAvailableBySlugs(array_keys($slugs)) as $product) {
            $cards[$slugs[$product->getSlug()]] = $this->twig->render('@c975LShop/ai_search/card.html.twig', ['product' => $product]);
        }

        return $cards;
    }

    // The slug of a product url, null for any other - the search being a POST, the urls are matched as the GET a visitor following them makes
    private function slug(string $url): ?string
    {
        $context = $this->router->getContext();
        $method = $context->getMethod();
        $context->setMethod('GET');
        try {
            $parameters = $this->router->match((string) parse_url($url, \PHP_URL_PATH));
        } catch (ExceptionInterface) {
            return null;
        } finally {
            $context->setMethod($method);
        }

        return \in_array($parameters['_route'] ?? null, ['product_display', 'product_display_localized'], true) ? ($parameters['slug'] ?? null) : null;
    }
}
