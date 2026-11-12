<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ProductItemStockAlertControllerTest extends TestCase
{
    // The real bot protection, a site-form-delay of 0 so a display then a submission in the same second is a human's, and a form submitted valid whenever it is handled
    private function createController(ProductItemStockAlertServiceInterface $stockAlertService, ?FormInterface $form = null): ProductItemStockAlertController
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn(0);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $rateLimiterGuard = $this->createStub(RateLimiterGuard::class);
        $rateLimiterGuard->method('isAcceptedForIp')->willReturn(true);

        $controller = new ProductItemStockAlertController(
            $stockAlertService,
            new FormBotProtection($config),
            $rateLimiterGuard,
            $translator,
        );

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->form(false));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/shop/product/the-book');

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $container->set('router', $urlGenerator);
        $container->set('request_stack', $this->requestStack);
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturnCallback(fn (): bool => $this->csrfValid);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $controller->setContainer($container);

        return $controller;
    }

    private RequestStack $requestStack;

    private bool $csrfValid = true;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    // A form answering submitted and valid once a POST is handled, never before
    private function form(bool $submitsValid): FormInterface
    {
        $handled = false;
        $email = $this->createStub(FormInterface::class);
        $email->method('getData')->willReturn('waiting@example.org');

        $form = $this->createStub(FormInterface::class);
        $form->method('getName')->willReturn('product_item_stock_alert');
        $form->method('handleRequest')->willReturnCallback(function (Request $request) use (&$handled, $form): FormInterface {
            $handled = $request->isMethod('POST');

            return $form;
        });
        $form->method('isSubmitted')->willReturnCallback(static function () use (&$handled, $submitsValid): bool {
            return $submitsValid && $handled;
        });
        $form->method('isValid')->willReturn($submitsValid);
        $form->method('get')->willReturn($email);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    // A request on the session given, carrying its cookie when the visitor already holds one
    private function request(string $method, Session $session, bool $withCookie): Request
    {
        $request = Request::create('/shop/stock-alert/1', $method, 'POST' === $method ? ['product_item_stock_alert' => ['email' => 'waiting@example.org']] : []);
        $request->setSession($session);
        if ($withCookie) {
            $request->cookies->set($session->getName(), 'session-id');
        }
        $this->requestStack->push($request);

        return $request;
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage(), flashes: new FlashBag());
    }

    // A visitor's first submission: the page displayed armed the timer, so the POST that follows subscribes
    public function testAPostAfterTheFormWasDisplayedSubscribes(): void
    {
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->once())->method('subscribe')->willReturn(true);
        $controller = $this->createController($stockAlertService, $this->form(true));
        $session = $this->session();

        $controller->new($this->request('GET', $session, false), $item = $this->item());
        $response = $controller->new($this->request('POST', $session, true), $item);

        $this->assertSame(302, $response->getStatusCode());
    }

    // A POST with a session but no display timed before it (a second tab, a session expired meanwhile) is shown back filled, with a word on why, and never run
    public function testAPostWithASessionButNoTimerIsShownBackWithoutSubscribing(): void
    {
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->never())->method('subscribe');
        $session = $this->session();

        $response = $this->createController($stockAlertService, $this->form(true))->new($this->request('POST', $session, true), $this->item());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['text.form_resend'], $session->getFlashBag()->get('warning'));
        // Armed by that display, so the visitor's next submission goes through
        $this->assertNotNull($session->get('shop_stock_alert_started_at'));
    }

    // A POST with neither session nor display never went through the form: dropped before the form even reads it
    public function testAPostWithNoSessionAndNoTimerIsDroppedUnread(): void
    {
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->never())->method('subscribe');
        $form = $this->createMock(FormInterface::class);
        $form->method('getName')->willReturn('product_item_stock_alert');
        $form->method('createView')->willReturn(new FormView());
        $form->expects($this->never())->method('handleRequest');

        $response = $this->createController($stockAlertService, $form)->new($this->request('POST', $this->session(), false), $this->item());

        $this->assertSame(200, $response->getStatusCode());
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
            ->new($this->request('GET', $this->session(), false), $this->item())
        ;

        $this->assertSame(200, $response->getStatusCode());
    }

    // A page composed against a stale card must not take an address for something nobody can reach any more
    public function testAnItemOfAHiddenProductIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new($this->request('GET', $this->session(), false), $this->item(productHidden: true))
        ;
    }

    public function testAnItemOfATrashedProductIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new($this->request('GET', $this->session(), false), $this->item(productDeleted: true))
        ;
    }

    public function testAnItemTakenOffSaleIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(ProductItemStockAlertServiceInterface::class))
            ->new($this->request('GET', $this->session(), false), $this->item(itemHidden: true))
        ;
    }

    // The alert the e-mail's link names
    private function stockAlert(): ProductItemStockAlert
    {
        return new ProductItemStockAlert()
            ->setProductItem($this->item())
            ->setEmail('waiting@example.org')
            ->setLocale('fr')
        ;
    }

    // The link in the recipient's own e-mail only asks: a link scanner opening it on arrival drops nothing
    public function testTheTokenLinkAsksWithoutUnsubscribing(): void
    {
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->never())->method('unsubscribe');

        $response = $this->createController($stockAlertService)->unsubscribe($this->request('GET', $this->session(), false), $this->stockAlert());

        $this->assertSame(200, $response->getStatusCode());
    }

    // The confirmation posted from that page drops the subscription
    public function testTheConfirmationPostedDropsTheSubscription(): void
    {
        $stockAlert = $this->stockAlert();
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->once())->method('unsubscribe')->with($stockAlert);

        $response = $this->createController($stockAlertService)->unsubscribe($this->request('POST', $this->session(), true), $stockAlert);

        $this->assertSame(200, $response->getStatusCode());
    }

    // A POST whose csrf token does not match (from anywhere else, or a session expired) is asked again, with a word on why
    public function testAPostWithoutAValidCsrfTokenIsAskedAgainAndSaysWhy(): void
    {
        $this->csrfValid = false;
        $stockAlertService = $this->createMock(ProductItemStockAlertServiceInterface::class);
        $stockAlertService->expects($this->never())->method('unsubscribe');
        $session = $this->session();

        $this->createController($stockAlertService)->unsubscribe($this->request('POST', $session, true), $this->stockAlert());

        $this->assertSame(['text.form_resend'], $session->getFlashBag()->get('warning'));
    }
}
