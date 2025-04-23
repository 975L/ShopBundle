<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\ConfirmOrderMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use c975L\ShopBundle\Service\EmailServiceInterface;

#[AsMessageHandler]
class ConfirmOrderMessageHandler
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly EmailServiceInterface $emailService,
    ) {}

    public function __invoke(ConfirmOrderMessage $message): void
    {
        $basket = $this->basketRepository->findOneById($message->getBasketId());
        if (!$basket) {
            return;
        }

        // Sends the email
        $this->emailService->confirmOrder($basket);
    }
}
