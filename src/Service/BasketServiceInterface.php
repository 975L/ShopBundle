<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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

    public function generateSecurityToken(): string;

    public function get(): ?Basket;

    public function getJson(): array;

    public function processStripePayment($session): void;

    public function itemsShipped(string $number): Basket;

    public function updateTotals(): void;

    public function validate(Request $request): string;

    public function paid(Basket $basket): void;
}


