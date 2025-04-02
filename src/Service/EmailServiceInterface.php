<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailServiceInterface
{
    public function create(): TemplatedEmail;

    public function getEmailConfig(): array;

    public function send($email);

    public function sendConfirmOrder(Basket $basket);

    public function sendDownloadInformation($basket, array $downloadLinks): void;

    public function sendStripeErrorMessage(Basket $basket, array $context): void;
}
