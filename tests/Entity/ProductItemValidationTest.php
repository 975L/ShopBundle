<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Entity;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// An empty row added to the prices of a product used to be saved as is, where the slug is written off the title and the title was not there
class ProductItemValidationTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    // The back-office submits its forms without the browser's own check, so the refusal has to come from the entity
    public function testAnItemWithoutATitleIsRefused(): void
    {
        $violations = $this->validator()->validate(new ProductItem(), null, null);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('title', $violations->get(0)->getPropertyPath());
    }

    // The description and the price fail through the same NOT NULL column as the title, and a 500 is not what the back-office is meant to answer with
    public function testAnItemWithoutADescriptionIsRefused(): void
    {
        $item = new ProductItem()->setTitle('Standard')->setPrice(1900);

        $violations = $this->validator()->validate($item, null, null);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('description', $violations->get(0)->getPropertyPath());
    }

    // NotNull and not NotBlank: an item given away costs 0, which the shop is entitled to publish
    public function testAnItemWithoutAPriceIsRefusedButAFreeOneIsNot(): void
    {
        $item = new ProductItem()->setTitle('Standard')->setDescription('Sold as is');

        $violations = $this->validator()->validate($item, null, null);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('price', $violations->get(0)->getPropertyPath());
        $this->assertCount(0, $this->validator()->validate($item->setPrice(0), null, null));
    }

    // Refusing the row is only worth something if the sheet it was added to looks inside its own collection
    public function testTheSheetValidatesEachOfItsItems(): void
    {
        $items = $this->validator()->getMetadataFor(Product::class)->getPropertyMetadata('items');

        $this->assertNotEmpty($items);
        $this->assertSame(CascadingStrategy::CASCADE, $items[0]->getCascadingStrategy());
    }
}
