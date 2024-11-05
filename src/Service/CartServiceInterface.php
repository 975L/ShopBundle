<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Cart;
use Symfony\Component\HttpFoundation\Request;

interface CartServiceInterface
{
    public function add(Request $request): array;

    public function create(): void;

    public function delete(): array;

    public function deleteSession(): void;

    public function define(): void;

    public function get(): Cart;

    public function getTotal(): array;

    public function updateTotals(): void;

    public function saveDatabase(): void;

    public function saveSession(): void;

    public function validate(Request $request): array;
}
