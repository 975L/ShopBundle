<?php

namespace c975L\ShopBundle\Service;

use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\HttpFoundation\Request;

interface BasketServiceInterface
{
    public function addProductItem(Request $request): array;

    public function create(): Basket;

    public function createForm(string $name, Basket $basket): Form;

    public function createPayment(): void;

    public function createStripeSession(): array;

    public function delete(): array;

    public function deleteUnvalidated(): void;

    public function deleteProductItem(Request $request): array;

    public function get(): ?Basket;

    public function getJson(): array;

    public function updateTotals(): void;

    public function validate(Request $request): string;

    public function validated(Basket $basket): void;
}
