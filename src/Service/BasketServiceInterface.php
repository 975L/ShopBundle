<?php

namespace c975L\ShopBundle\Service;

use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\HttpFoundation\Request;

interface BasketServiceInterface
{
    public function add(Request $request): array;

    public function create(): void;

    public function createForm(string $name, Basket $basket): Form;

    public function delete(): array;

    public function deleteProduct(Request $request): array;

    public function deleteSession(): void;

    public function define(): void;

    public function get(): Basket;

    public function getTotal(): array;

    public function updateTotals(): void;

    public function saveDatabase(): void;

    public function saveSession(): void;

    public function validate(Request $request): array;
}
