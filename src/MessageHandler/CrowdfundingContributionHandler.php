<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\MessageHandler;

use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Message\CrowdfundingContributionMessage;
use c975L\ShopBundle\Repository\BasketRepository;
use c975L\ShopBundle\Service\EmailServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CrowdfundingContributionHandler
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly EmailServiceInterface $emailService
    ) {
    }

    public function __invoke(CrowdfundingContributionMessage $message): void
    {
        $basket = $this->basketRepository->findOneById($message->getBasketId());

        if (!$basket) {
            return;
        }

        // Gets counterparts
        $counterparts = [];
        $items = $basket->getItems();

        if (isset($items['crowdfunding'])) {
            $counterparts = $items['crowdfunding'];
        }

        // Sends the email with counterparts
        if (!empty($counterparts)) {
            $this->emailService->crowdfundingContribution($basket, $counterparts);
        }

    }
}