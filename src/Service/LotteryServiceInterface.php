<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Lottery;
use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Entity\LotteryTicket;
use c975L\ShopBundle\Entity\CrowdfundingContributor;
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;

interface LotteryServiceInterface
{
    public function generateTicketsForContributor(CrowdfundingContributor $contributor, CrowdfundingCounterpart $counterpart, int $quantity): array;

    public function generateTicketNumber(): string;

    public function drawWinner(Lottery $lottery, $prizeRank): ?LotteryTicket;
}