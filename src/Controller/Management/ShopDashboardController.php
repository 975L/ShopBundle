<?php

namespace c975L\ShopBundle\Controller\Management;

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
        // return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
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
