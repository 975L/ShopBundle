<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopBlockChoices;
use PHPUnit\Framework\TestCase;

// The lists the block forms pick from - keyed by label, which is where two rows sharing a title would collapse into one
class ShopBlockChoicesTest extends TestCase
{
    public function testAProductIsOfferedByTitleAndStoredBySlug(): void
    {
        $choices = $this->choices([$this->product('Affiche', 'affiche')], []);

        $this->assertSame(['Affiche' => 'affiche'], $choices->products());
    }

    public function testTwoProductsSharingATitleAreDisambiguatedBySlug(): void
    {
        $choices = $this->choices([$this->product('Affiche', 'affiche'), $this->product('Affiche', 'affiche-2')], []);

        $this->assertSame(['Affiche (affiche)' => 'affiche', 'Affiche (affiche-2)' => 'affiche-2'], $choices->products());
    }

    public function testACategoryIsOfferedByNameAndStoredBySlug(): void
    {
        $choices = $this->choices([], [$this->category('Affiches', 'affiches')]);

        $this->assertSame(['Affiches' => 'affiches'], $choices->categories());
    }

    private function choices(array $products, array $categories): ShopBlockChoices
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findNotDeleted')->willReturn($products);

        $categoryRepository = $this->createStub(ProductCategoryRepository::class);
        $categoryRepository->method('findAll')->willReturn($categories);

        return new ShopBlockChoices($productRepository, $categoryRepository);
    }

    private function product(string $title, string $slug): Product
    {
        return new Product()->setTitle($title)->setSlug($slug);
    }

    private function category(string $name, string $slug): ProductCategory
    {
        return new ProductCategory()->setName($name)->setSlug($slug);
    }
}
