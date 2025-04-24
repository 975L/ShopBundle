<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\LotteryWinningTicketMessage;
use c975L\ShopBundle\Repository\LotteryPrizeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use c975L\ShopBundle\Service\EmailServiceInterface;

#[AsMessageHandler]
class LotteryWinningTicketMessageHandler
{
    public function __construct(
        private readonly LotteryPrizeRepository $lotteryPrizeRepository,
        private readonly EmailServiceInterface $emailService,
    ) {}

    public function __invoke(LotteryWinningTicketMessage $message): void
    {
        $prize = $this->lotteryPrizeRepository->findOneById($message->getPrizeId());
        if (!$prize) {
            return;
        }

        // Sends the email
        $this->emailService->lotteryWinningTicket($prize);
    }
}
