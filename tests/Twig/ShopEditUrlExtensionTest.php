<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Twig\Extension\ShopEditUrlExtension;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class ShopEditUrlExtensionTest extends TestCase
{
    // Every setter returns the generator itself, and generateUrl() echoes back the entity and the focused field it was given - what matters here is what the button points at
    private function createAdminUrlGenerator(bool $throwing = false): AdminUrlGeneratorInterface
    {
        $entityId = null;
        $focusField = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnCallback(function (mixed $value) use ($generator, &$entityId) {
            $entityId = $value;

            return $generator;
        });
        $generator->method('set')->willReturnCallback(function (string $key, mixed $value) use ($generator, &$focusField) {
            $focusField = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use ($throwing, &$entityId, &$focusField): string {
            if ($throwing) {
                throw new \RuntimeException('No admin route in cache');
            }

            return '/admin/' . $entityId . (null === $focusField ? '' : '?focusField=' . $focusField);
        });

        return $generator;
    }

    private function productWithId(?int $id): Product
    {
        $product = new Product();
        if (null !== $id) {
            new \ReflectionProperty(Product::class, 'id')->setValue($product, $id);
        }

        return $product;
    }

    private function categoryWithId(int $id): ProductCategory
    {
        $category = new ProductCategory();
        new \ReflectionProperty(ProductCategory::class, 'id')->setValue($category, $id);

        return $category;
    }

    // A card leads to the product's own edit screen, nothing focused
    public function testGetProductEditUrlPointsAtTheProduct(): void
    {
        $extension = new ShopEditUrlExtension($this->createAdminUrlGenerator());

        $this->assertSame('/admin/7', $extension->getProductEditUrl($this->productWithId(7)));
    }

    // A section of the sheet leads to the very field it is printed from (see UiBundle's field-focus.js)
    public function testGetProductEditUrlFocusesTheGivenField(): void
    {
        $extension = new ShopEditUrlExtension($this->createAdminUrlGenerator());

        $this->assertSame('/admin/7?focusField=medias', $extension->getProductEditUrl($this->productWithId(7), 'medias'));
    }

    // A category page leads to the category's own edit screen, its text to the field it is printed from
    public function testGetProductCategoryEditUrlPointsAtTheCategory(): void
    {
        $extension = new ShopEditUrlExtension($this->createAdminUrlGenerator());

        $this->assertSame('/admin/3?focusField=description', $extension->getProductCategoryEditUrl($this->categoryWithId(3), 'description'));
    }

    // An entity with no id has no screen to point at
    public function testGetProductEditUrlReturnsNullForUnsavedProduct(): void
    {
        $extension = new ShopEditUrlExtension($this->createAdminUrlGenerator());

        $this->assertNull($extension->getProductEditUrl($this->productWithId(null)));
    }

    // EasyAdmin's route cache being empty makes generateUrl() throw on a public page - the button is dropped rather than the page
    public function testGetProductEditUrlReturnsNullWhenUrlCannotBeBuilt(): void
    {
        $extension = new ShopEditUrlExtension($this->createAdminUrlGenerator(true));

        $this->assertNull($extension->getProductEditUrl($this->productWithId(7)));
    }
}
