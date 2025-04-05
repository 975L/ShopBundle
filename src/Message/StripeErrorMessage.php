<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Message;

class StripeErrorMessage
{
    public function __construct(
        private readonly string $sessionId,
        private readonly ?string $basketId,
        private readonly string $errorMessage,
        private readonly string $errorTrace
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getBasketId(): ?string
    {
        return $this->basketId;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getErrorTrace(): string
    {
        return $this->errorTrace;
    }
}