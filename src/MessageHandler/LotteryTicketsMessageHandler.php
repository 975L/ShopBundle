<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Message\LotteryTicketsMessage;
use c975L\ShopBundle\Repository\CrowdfundingContributorRepository;
use c975L\ShopBundle\Repository\LotteryTicketRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use c975L\ShopBundle\Service\EmailServiceInterface;

#[AsMessageHandler]
class LotteryTicketsMessageHandler
{
    public function __construct(
        private readonly CrowdfundingContributorRepository $crowdfundingContributorRepository,
        private readonly LotteryTicketRepository $lotteryTicketRepository,
        private readonly EmailServiceInterface $emailService,
    ) {}

    public function __invoke(LotteryTicketsMessage $message): void
    {
        $lotterytickets = $this->lotteryTicketRepository->findByContributor($message->getContributorId());
        if (!$lotterytickets) {
            return;
        }

        $contributor = $this->crowdfundingContributorRepository->findOneById($message->getContributorId());
        if (!$contributor) {
            return;
        }

        $email = $contributor->getEmail();

        // Defines tickets
        $tickets = [];
        foreach ($lotterytickets as $lotteryTicket) {
            $tickets[] = [
                'lotteryIdentifier' => $lotteryTicket->getLottery()->getIdentifier(),
                'number' => $lotteryTicket->getNumber(),
                'drawDate' => $lotteryTicket->getLottery()->getDrawDate(),
                'crowdfunding' => $lotteryTicket->getCounterpart()->getCrowdfunding()->__toString(),
                'slug' => $lotteryTicket->getCounterpart()->getCrowdfunding()->getSlug(),
            ];
        }

        // Sends the email with lottery tickets
        if (!empty($tickets)) {
            $this->emailService->lotteryTickets($email, $tickets);
        }
    }
}