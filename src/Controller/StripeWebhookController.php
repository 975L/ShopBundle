<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use c975L\ShopBundle\Service\BasketServiceInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly BasketServiceInterface $basketService,
        private readonly ConfigServiceInterface $configService
    ) {
    }

    #[Route('/shop/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $stripeSecret = $this->configService->getParameter('c975LShop.stripeSecret');
        $webhookSecret = $this->configService->getParameter('c975LShop.stripeWebhookSecret');

        Stripe::setApiKey($stripeSecret);

        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;

                $basketId = $session->metadata->basket_id ?? null;

                if ($basketId) {
                    $this->basketService->processStripePayment($session);
                }
            }

            return new Response('Webhook received', 200);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        } catch (\Exception $e) {
            return new Response('Error: ' . $e->getMessage(), 500);
        }
    }
}