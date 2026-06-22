<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\AbstractMenuProvider;
use c975L\ShopBundle\Controller\Management\BasketCrudController;
use c975L\ShopBundle\Controller\Management\CrowdfundingCrudController;
use c975L\ShopBundle\Controller\Management\PaymentCrudController;
use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;

class MenuProvider extends AbstractMenuProvider
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.shop',
            'translation_domain' => 'shop',
        ];
    }

    public function getRouteSection(): array
    {
        return [
            'label' => 'label.links',
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
            ],
            'product' => [
                'controller' => ProductCrudController::class,
                'label' => 'label.products',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-shop',
            ],
            'crowdfunding' => [
                'controller' => CrowdfundingCrudController::class,
                'label' => 'label.crowdfundings',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-money-bill',
            ],
            'basket' => [
                'controller' => BasketCrudController::class,
                'label' => 'label.baskets',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-basket-shopping',
            ],
            'payment' => [
                'controller' => PaymentCrudController::class,
                'label' => 'label.payments',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-money-bill-wave',
            ],
        ];
    }

    public function getRoutes(): array
    {
        return [
            'shop' => [
                'label' => 'label.shop',
                'name' => 'shop_index',
                'translation_domain' => 'shop',
                'icon' => '',
            ],
            'crowdfunding' => [
                'label' => 'label.crowdfundings',
                'name' => 'crowdfunding_index',
                'translation_domain' => 'shop',
                'icon' => '',
            ],
        ];
    }


}
