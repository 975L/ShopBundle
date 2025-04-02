<?php

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

        $this->emailService->sendStripeErrorMessage($basket, $context);
    }
}