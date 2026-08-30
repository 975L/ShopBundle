<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\ShopBundle\Controller\ProductItemStockAlertController;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Service\ProductItemStockAlertServiceInterface;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ProductItemStockAlertControllerTest extends TestCase
{
    private function createController(ProductItemStockAlertServiceInterface $stockAlertService): ProductItemStockAlertController
    {
        $botProtection = $this->createStub(FormBotProtection::class);
        $botProtection->method('isSuspicious')->willReturn(false);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('a message');

        $controller = new ProductItemStockAlertController(
            $stockAlertService,
            $botProtection,
            $this->createStub(RateLimiterGuard::class),
            $translator,
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('getName')->willReturn('product_item_stock_alert');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        return $controller;
    }

    private function item(bool $productHidden = false, bool $itemHidden = false, bool $productDeleted = false): ProductItem
    {
        $product = new Product()
            ->setTitle('The book')
            ->setSlug('the-book')
            ->setHidden($productHidden)
        ;

        if ($productDeleted) {
            $product->setIsDeleted(true);
        }

        return new ProductItem()
            ->setTitle('Paperback')
            ->setLimitedQuantity(5)
            ->setOrderedQuantity(5)
            ->setHidden($itemHidden)
            ->setProduct($product)
        ;
    }

    public function testTheFormIsShownOnAnItemOfAProductTheShopStillShows(): void
    {
        $response = $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new(new Request(), $this->item())
        ;

        $this->assertSame(200, $response->getStatusCode());
    }

    // A page composed against a stale card must not take an address for something nobody can reach any more
    public function testAnItemOfAHiddenProductIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new(new Request(), $this->item(productHidden: true))
        ;
    }

    public function testAnItemOfATrashedProductIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new(new Request(), $this->item(productDeleted: true))
        ;
    }

    public function testAnItemTakenOffSaleIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new(new Request(), $this->item(itemHidden: true))
        ;
    }

    // The link in the recipient's own e-mail: one click, no confirmation step, which is what keeps people from marking the message as spam instead
    public function testTheTokenLinkDropsTheSubscriptionAndSaysSo(): void
    {
        $stockAlert = new ProductItemStockAlert()
            ->setProductItem($this->item())
            ->setEmail('waiting@example.org')
            ->setLocale('fr')
        ;

        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->once())->method('unsubscribe')->with($stockAlert);

        $response = $this->createController($stockAlertService)->unsubscribe($stockAlert);

        $this->assertSame(200, $response->getStatusCode());
    }
}
