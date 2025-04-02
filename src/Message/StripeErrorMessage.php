<?php

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