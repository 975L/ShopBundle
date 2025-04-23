<?php

namespace c975L\ShopBundle\Service;

use DateTime;
use DateTimeImmutable;
use c975L\ShopBundle\Entity\Lottery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\ByteString;
use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Entity\LotteryTicket;
use c975L\ShopBundle\Repository\LotteryRepository;
use c975L\ShopBundle\Message\LotteryTicketsMessage;
use c975L\ShopBundle\Entity\CrowdfundingContributor;
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use Symfony\Component\Messenger\MessageBusInterface;
use c975L\ShopBundle\Repository\LotteryTicketRepository;

class LotteryService implements LotteryServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LotteryRepository $lotteryRepository,
        private readonly LotteryTicketRepository $ticketRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    // Creates a new lottery for a crowdfunding campaign
    public function createLotteryForCrowdfunding(Crowdfunding $crowdfunding): Lottery
    {
        // Check if a lottery already exists
        if ($crowdfunding->getLottery() !== null) {
            return $crowdfunding->getLottery();
        }

        $lottery = new Lottery();
        $lottery->setCrowdfunding($crowdfunding);
        $lottery->setIdentifier($this->generateIdentifier());
        $lottery->setIsActive(true);

        $this->entityManager->persist($lottery);
        $this->entityManager->flush();

        return $lottery;
    }

    // Generates a unique identifier for the lottery - Format: LOT-XXXX-XXXX (préfixe + 9 caractères aléatoires)
    public function generateIdentifier(): string
    {
        $prefix = 'LOT';
        $randomPart1 = strtoupper(bin2hex(random_bytes(2)));
        $randomPart2 = strtoupper(bin2hex(random_bytes(2)));

        return $prefix . '-' . $randomPart1 . '-' . $randomPart2;
    }

    // Generates tickets for a contributor based on their purchase
    public function generateTicketsForContributor(CrowdfundingContributor $contributor, CrowdfundingCounterpart $counterpart): array
    {
        $tickets = [];
        $crowdfunding = $contributor->getCrowdfunding();

        // Generates tickets
        if ($counterpart->getLotteryTickets() > 0) {
            $lottery = $crowdfunding->getLotteries()[0];
            $ticketsToGenerate = $counterpart->getLotteryTickets();
            for ($i = 0; $i < $ticketsToGenerate; $i++) {
                $ticket = new LotteryTicket();
                $ticket->setLottery($lottery);
                $ticket->setContributor($contributor);
                $ticket->setCounterpart($counterpart);
                $ticket->setNumber($this->generateTicketNumber());
                $ticket->setCreation(new DateTimeImmutable());

                $this->entityManager->persist($ticket);
                $tickets[] = $ticket;
            }
        }

        $this->entityManager->flush();

        // Sends email to contributor with tickets numebr
        $this->messageBus->dispatch(new LotteryTicketsMessage($contributor->getId()));

        return $tickets;
    }

    // Generates a unique ticket number - Format: XX-YYYY-ZZZ (2 letters, 4 numbers, 3 letters)
    public function generateTicketNumber(): string
    {
        $attempts = 0;
        $maxAttempts = 20;

        do {
            // Generate random parts
            $part1 = strtoupper(ByteString::fromRandom(2, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());
            $part2 = sprintf('%04d', random_int(1, 9999));
            $part3 = strtoupper(ByteString::fromRandom(3, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());

            $number = $part1 . '-' . $part2 . '-' . $part3;

            // Check if ticket number already exists
            $exists = $this->ticketRepository->findOneByNumber([$number]);

            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        return $number;
    }

    // Draws a random winner for a prize
    public function drawWinner(Lottery $lottery, $prizeRank): ?LotteryTicket
    {
        // Finds the prize
        $prize = $lottery->getPrizes()
            ->filter(function($p) use ($prizeRank) {
                return $p->getRank() === $prizeRank;
            })
            ->first()
        ;

        // Draws the lottery for the specified rank prize
        $winningTicket = $prize->getWinningTicket();
        if (null === $winningTicket) {
            // Gets all tickets
            $tickets = $this->ticketRepository->findByLottery($lottery);
            if (empty($tickets)) {
                return null;
            }

            // Selects a random ticket
            $winnerIndex = array_rand($tickets);
            $winningTicket = $tickets[$winnerIndex];

            // Defines the winning ticket for the prize
            if ($prize) {
                $prize->setWinningTicket($winningTicket);
                $prize->setDrawDate(new DateTimeImmutable());
                $this->entityManager->flush();
            }
// Sends email



        }

        return $winningTicket;
    }
}