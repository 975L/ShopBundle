<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Controller\Management\ShopSettingsCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.shop',
            'translation_domain' => 'shop',
        ];
    }

    public function getMenus(): array
    {
        return [
            'product_category' => [
                'controller' => ProductCategoryCrudController::class,
                'label' => 'label.categories',
                'narration' => 'narration.categories',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_product_category',
            ],
            'product' => [
                'controller' => ProductCrudController::class,
                'label' => 'label.products',
                'narration' => 'narration.products',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_product',
            ],
            'shop_settings' => [
                'controller' => ShopSettingsCrudController::class,
                'label' => 'label.shop_index',
                'narration' => 'narration.shop_index',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_shop_index',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [
            // The description is the sentence the shop's index falls back to, not necessarily the one it prints: a shop that has written its own intro on the back-office index screen shows that one instead (see ShopSettingsCrudController and templates/shop/index.html.twig). Reading the row here would cost the menu a query and hand it a description that is no longer a translation key
            'shop' => [
                'label' => 'label.shop',
                'narration' => 'narration.shop',
                'name' => 'shop_index',
                'translation_domain' => 'shop',
                'icon' => '',
                'description' => 'label.info_shop_link',
            ],
        ];
    }
}
