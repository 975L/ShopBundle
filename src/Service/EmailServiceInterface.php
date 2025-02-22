<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\Mime\Email;

interface EmailServiceInterface
{
    public function send(Basket $basket);
}
