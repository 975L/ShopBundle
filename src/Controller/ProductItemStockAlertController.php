<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Form\ProductItemStockAlertType;
use c975L\ShopBundle\Service\ProductItemStockAlertServiceInterface;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// A page of its own rather than a form on the product sheet, for the same reason ReviewController is one: the sheet's html is handed to a shared cache per fragment, where a form needs a session, a csrf token and a Set-Cookie - the three things that must never travel with a cached page
class ProductItemStockAlertController extends AbstractController
{
    private const string SESSION_KEY = 'shop_stock_alert_started_at';

    public function __construct(
        private readonly ProductItemStockAlertServiceInterface $stockAlertService,
        private readonly FormBotProtection $botProtection,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly TranslatorInterface $translator,
        private readonly ?RateLimiterFactoryInterface $stockAlertLimiterFactory = null,
    ) {
    }

    // SUBSCRIBE
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/shop/stock-alert/{id:productItem}',
        name: 'shop_stock_alert_new',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function new(Request $request, ProductItem $productItem): Response
    {
        $product = $productItem->getProduct();

        // Nothing is offered on what the shop does not show, nor on an item still buyable or withdrawn rather than sold out: a stale link is answered with the sheet, which says what the item's real state is
        if (null === $product || $product->isHidden() || $product->isDeleted() || $productItem->isHidden()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ProductItemStockAlertType::class);

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same answer and no hint; a submission with no timed display (a second tab, a session expired while the visitor typed) is shown back filled and never run - the same reading as UiBundle's FormController
        [$untimed, $suspicious] = $this->classifySubmission($request, $form->getName());

        if (!$suspicious) {
            $form->handleRequest($request);
        }

        if ($untimed) {
            $this->addFlash('warning', $this->translator->trans('text.form_resend', [], 'ui'));
        }

        if (!$untimed && !$suspicious && $form->isSubmitted() && $form->isValid()) {
            $answered = $this->subscribe($request, $productItem, (string) $form->get('email')->getData());
            if ($answered) {
                return $this->redirectToRoute('product_display', ['slug' => $product->getSlug()]);
            }
        }

        // Armed at display, once the submission is handled and never before isSuspicious() on the same request: a page shown again after a typo is timed from that very display
        $this->botProtection->startTimer($request, self::SESSION_KEY);

        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render('@c975LShop/stock_alert/new.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
            'productItem' => $productItem,
        ]);
    }

    // Whether a POST comes with no timed display (shown back filled) or looks like a bot (dropped), as [untimed, suspicious] - isUntimed() asked first, isSuspicious() consuming the timer
    /** @return array{bool, bool} */
    private function classifySubmission(Request $request, string $formName): array
    {
        if (!$request->isMethod('POST')) {
            return [false, false];
        }

        if ($this->botProtection->isUntimed($request, self::SESSION_KEY)) {
            return [true, false];
        }

        return [false, $this->botProtection->isSuspicious($request, $formName, self::SESSION_KEY)];
    }

    // Records the alert, or says it is not awaited, true once the visitor was answered; false when the caller hit the ceiling, the form then shown again with the warning
    private function subscribe(Request $request, ProductItem $productItem, string $email): bool
    {
        // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
        $clientIp = $request->getClientIp();

        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->stockAlertLimiterFactory, $clientIp)) {
            $this->addFlash('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));

            return false;
        }

        $subscribed = $this->stockAlertService->subscribe($productItem, $email, $request->getLocale());

        $this->addFlash(
            $subscribed ? 'success' : 'warning',
            $this->translator->trans($subscribed ? 'text.stock_alert_subscribed' : 'text.stock_alert_not_awaited', [], 'shop')
        );

        return true;
    }

    // UNSUBSCRIBE - the link in the recipient's own e-mail opens a confirmation, the POST it sends unsubscribing
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/shop/stock-alert/{token:stockAlert}/unsubscribe',
        name: 'shop_stock_alert_unsubscribe',
        requirements: ['token' => '[a-zA-Z0-9]{16}'],
        methods: ['GET', 'POST']
    )]
    public function unsubscribe(Request $request, ProductItemStockAlert $stockAlert): Response
    {
        // A GET only asks: link scanners open every link of an e-mail on its arrival, and would drop the alert before anyone read it
        if (!$request->isMethod('POST')) {
            return $this->render('@c975LShop/stock_alert/unsubscribe.html.twig');
        }

        // A session expired, or cookies refused by a mail app's browser, is told why the page came back rather than left clicking
        if (!$this->isCsrfTokenValid('shop_stock_alert_unsubscribe', (string) $request->request->get('_token'))) {
            $this->addFlash('warning', $this->translator->trans('text.form_resend', [], 'ui'));

            return $this->render('@c975LShop/stock_alert/unsubscribe.html.twig');
        }

        $this->stockAlertService->unsubscribe($stockAlert);

        return $this->render('@c975LShop/stock_alert/unsubscribed.html.twig');
    }
}
