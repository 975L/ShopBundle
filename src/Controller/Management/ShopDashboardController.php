<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\Payment;
use c975L\ShopBundle\Entity\Product;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/shop/management', routeName: 'shop_management')]
#[IsGranted('ROLE_ADMIN')]
class ShopDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('@c975LShop/management/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/favicon.ico"> Papa Câlin')
            ->setFaviconPath('/favicon.ico')
            ->setTranslationDomain('shop');
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('label.dashboard', 'fa fa-home')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('label.products', 'fas fa-shop', Product::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('label.baskets', 'fas fa-basket-shopping', Basket::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('label.payments', 'fas fa-money-bill-wave', Payment::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToUrl('label.shop', 'fas fa-home', $this->generateUrl('shop_index'));
        yield MenuItem::linkToLogout('label.signout', 'fa fa-exit');
    }
}
