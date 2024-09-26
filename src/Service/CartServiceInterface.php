<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Cart;
use Symfony\Component\HttpFoundation\Request;

interface CartServiceInterface
{
    public function add(Request $request): void;

    public function create(): void;

    public function define(): void;

    public function deleteInSession(): void;

    public function get(): Cart;

    public function updateTotals(): void;

    public function saveDatabase(): void;

    public function saveSession(): void;
}
