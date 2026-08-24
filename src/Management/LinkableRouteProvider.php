<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// Exposes the shop's public pages as SiteBundle Menu targets: the catalog itself, and one entry per category - only the target is stored, the url being generated at render time, so a renamed route prefix or slug leaves no menu item behind
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    // What a category entry is keyed on, its id following - the menu item stores it as "route:shop_category.12"
    public const CATEGORY_PREFIX = 'shop_category.';

    public function __construct(
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getLinkableRoutes(): array
    {
        $routes = [
            'shop_index' => [
                'label' => 'label.shop',
                'translation_domain' => 'shop',
            ],
        ];

        $category = $this->translator->trans('label.category', [], 'shop');

        // Every category the shop lists, keyed by id rather than by slug so a renamed one keeps the menu item pointing at it
        foreach ($this->productCategoryRepository->findAll() as $entity) {
            $routes[self::CATEGORY_PREFIX . $entity->getId()] = [
                // The shop's own wording, not a key to translate - shown as it is in the rendered menu
                'label' => (string) $entity->getName(),
                'translation_domain' => false,
                // The picker holds it among every page of the site, so it says what it is there, and the categories sit together once the list is sorted
                'picker_label' => $category . ' - ' . $entity->getName(),
                'route' => 'category_display',
                'params' => ['slug' => (string) $entity->getSlug()],
            ];
        }

        return $routes;
    }
}
