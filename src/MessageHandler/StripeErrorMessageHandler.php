<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\StripeErrorMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use c975L\ShopBundle\Service\EmailServiceInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class StripeErrorMessageHandler
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly EmailServiceInterface $emailService,
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(StripeErrorMessage $message): void
    {
        $basket = $this->basketRepository->findOneById($message->getBasketId());
        if (!$basket) {
            return;
        }

        $context = [
            'session_id' => $message->getSessionId(),
            'basket_id' => $message->getBasketId(),
            'error_message' => $message->getErrorMessage(),
            'error_trace' => $message->getErrorTrace(),
            'basket' => $basket,
            'date' => new \DateTime(),
        ];

        $this->emailService->stripeErrorMessage($basket, $context);
    }
}