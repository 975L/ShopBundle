<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Message;

class LotteryWinningTicketMessage
{
    public function __construct(
        private int $prizeId
    ) {}

    public function getPrizeId(): int
    {
        return $this->prizeId;
    }
}