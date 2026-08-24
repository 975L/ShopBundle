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
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_product_category',
            ],
            'product' => [
                'controller' => ProductCrudController::class,
                'label' => 'label.products',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_product',
            ],
            'shop_settings' => [
                'controller' => ShopSettingsCrudController::class,
                'label' => 'label.shop_index',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
                'description' => 'label.info_shop_index',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [
            'shop' => [
                'label' => 'label.shop',
                'name' => 'shop_index',
                'translation_domain' => 'shop',
                'icon' => '',
                'description' => 'label.info_shop_link',
            ],
        ];
    }
}
