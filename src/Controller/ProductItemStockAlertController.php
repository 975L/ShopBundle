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

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same answer and no hint - same reading as ReviewController's
        $suspicious = $request->isMethod('POST')
            && $this->botProtection->isSuspicious($request, $form->getName(), self::SESSION_KEY);

        if (!$suspicious) {
            $form->handleRequest($request);
        }

        if (!$suspicious && $form->isSubmitted() && $form->isValid()) {
            // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
            $clientIp = $request->getClientIp();

            if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->stockAlertLimiterFactory, $clientIp)) {
                $this->addFlash('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));
            } else {
                $subscribed = $this->stockAlertService->subscribe($productItem, (string) $form->get('email')->getData(), $request->getLocale());

                $this->addFlash(
                    $subscribed ? 'success' : 'warning',
                    $this->translator->trans($subscribed ? 'text.stock_alert_subscribed' : 'text.stock_alert_not_awaited', [], 'shop')
                );

                return $this->redirectToRoute('product_display', ['slug' => $product->getSlug()]);
            }
        }

        // Armed here rather than before isSuspicious(), which consumes the key on every submission: a page displayed again after a typo would otherwise re-arm the timer to now and read 0 second elapsed, turning every correction into a suspicious submission
        $this->botProtection->startTimer($request, self::SESSION_KEY);

        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render('@c975LShop/stock_alert/new.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
            'productItem' => $productItem,
        ]);
    }

    // UNSUBSCRIBE
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/shop/stock-alert/{token:stockAlert}/unsubscribe',
        name: 'shop_stock_alert_unsubscribe',
        requirements: ['token' => '[a-zA-Z0-9]{16}'],
        methods: ['GET']
    )]
    public function unsubscribe(ProductItemStockAlert $stockAlert): Response
    {
        // No confirmation step: the link is in the recipient's own e-mail and unsubscribing is what they asked for, where a second click is what makes people give up and mark the message as spam instead
        $this->stockAlertService->unsubscribe($stockAlert);

        return $this->render('@c975LShop/stock_alert/unsubscribed.html.twig');
    }
}
