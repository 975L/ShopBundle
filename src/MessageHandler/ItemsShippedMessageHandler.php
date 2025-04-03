<?php

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\ItemsShippedMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use c975L\ShopBundle\Service\EmailServiceInterface;

#[AsMessageHandler]
class ItemsShippedMessageHandler
{
    public function __construct(
        private BasketRepository $basketRepository,
        private readonly EmailServiceInterface $emailService,
    ) {}

    public function __invoke(ItemsShippedMessage $message): void
    {
        $basket = $this->basketRepository->findOneById($message->getBasketId());
        if (!$basket) {
            return;
        }

        // Sends the email
        $this->emailService->sendShippedItems($basket);
    }
}