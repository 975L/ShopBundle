<?php

namespace c975L\ShopBundle\Message;

class ProductItemDownloadMessage
{
    public function __construct(
        private int $basketId
    ) {}

    public function getBasketId(): int
    {
        return $this->basketId;
    }
}