<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;

interface ProductItemStockAlertServiceInterface
{
    /**
     * Puts one address on the waiting list of one item, and says whether it is now on it.
     *
     * False on an item nobody can be waiting for - one still in stock, or one withdrawn rather than sold out -
     * which is what a page offering the form on a stale card would ask for. Subscribing twice is not an error: the
     * existing row goes back to waiting rather than a second one being refused.
     */
    public function subscribe(ProductItem $productItem, string $email, string $locale): bool;

    /**
     * Takes one subscription off the list, by the token its alert e-mail carried.
     */
    public function unsubscribe(ProductItemStockAlert $stockAlert): void;

    /**
     * Tells the next batch of subscribers whose item has come back, and returns how many were told.
     *
     * Bounded on purpose: a restocked best-seller can carry thousands of subscriptions, and sending them in one
     * pass would hold the mailer - and the run - for as long as it takes. The queue is walked run after run.
     */
    public function notifyPending(int $limit): int;

    /**
     * How many subscriptions are still waiting to be told, whatever the state of their item.
     */
    public function countPending(): int;
}
