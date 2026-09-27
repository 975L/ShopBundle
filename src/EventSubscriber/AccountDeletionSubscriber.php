<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\EventSubscriber;

use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// An account anonymized, by its owner or by the inactivity cleanup, takes the stock alerts of its former address with it
class AccountDeletionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ProductItemStockAlertRepository $stockAlertRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserAnonymizedEvent::class => 'onUserAnonymized',
        ];
    }

    // Deletes the alerts the address subscribed to
    public function onUserAnonymized(UserAnonymizedEvent $event): void
    {
        if (null !== $event->email) {
            $this->stockAlertRepository->deleteByEmail($event->email);
        }
    }
}
