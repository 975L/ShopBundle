<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Entity\LotteryPrize;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailServiceInterface
{
    public function create(): TemplatedEmail;

    public function getConfig(): array;

    public function send($email);

    public function confirmOrder(Basket $basket);

    public function crowdfundingContribution(Basket $basket, array $counterparts): void;

    public function downloadInformation(Basket $basket, array $downloadLinks): void;

    public function lotteryTickets(string $emailAddress, array $tickets);

    public function lotteryWinningTicket(LotteryPrize $prize);

    public function shippedItems(Basket $basket, string $type);

    public function stripeErrorMessage(Basket $basket, array $context): void;
}
