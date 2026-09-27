<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ShopBundle\EventSubscriber\AccountDeletionSubscriber;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use PHPUnit\Framework\TestCase;

class AccountDeletionSubscriberTest extends TestCase
{
    // The address the account held before anonymization is the one whose alerts go
    public function testAnAnonymizedAccountHasItsStockAlertsDeleted(): void
    {
        $repository = $this->createMock(ProductItemStockAlertRepository::class);
        $repository->expects($this->once())->method('deleteByEmail')->with('user@example.test')->willReturn(2);

        new AccountDeletionSubscriber($repository)->onUserAnonymized(new UserAnonymizedEvent($this->createStub(InactivityAwareInterface::class), 'user@example.test'));
    }

    // Listening to the event ConfigBundle dispatches, from the account page and from the inactivity cleanup alike
    public function testItListensToTheAnonymization(): void
    {
        $this->assertArrayHasKey(UserAnonymizedEvent::class, AccountDeletionSubscriber::getSubscribedEvents());
    }
}
