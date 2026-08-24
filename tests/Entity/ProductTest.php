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

// The two states a product is read through: what the shop stands behind, and what it has taken back
class ProductTest extends TestCase
{
    // A product is written before it is sold, so it starts out of the catalogue whatever the column default says for the rows that were there before it
    public function testANewProductIsADraft(): void
    {
        $product = new Product();

        $this->assertFalse($product->isPublished());
        $this->assertFalse($product->isDeleted());
    }

    // A row of the recycle bin is out of the catalogue whatever its own switch said before, the two never disagreeing
    public function testTrashingAProductUnpublishesIt(): void
    {
        $product = new Product()->setIsPublished(true);

        $product->setIsDeleted(true);

        $this->assertFalse($product->isPublished());
    }

    // Restoring gives back the content, not the audience: the product comes back as a draft, to be read once before it is published again
    public function testRestoringAProductLeavesItADraft(): void
    {
        $product = new Product()->setIsPublished(true)->setIsDeleted(true);

        $product->setIsDeleted(false);

        $this->assertFalse($product->isDeleted());
        $this->assertFalse($product->isPublished());
    }

    // An item is written to be sold, unlike the product carrying it: a format typed in the back-office is on the sheet as soon as it is saved
    public function testANewItemIsOnSale(): void
    {
        $this->assertTrue(new ProductItem()->isPublished());
    }

    // What the sheet lists, the back-office still holding them all
    public function testOnlyThePublishedItemsAreReadOffTheProduct(): void
    {
        $product = new Product();
        $product->addItem(new ProductItem()->setTitle('A3'));
        $product->addItem(new ProductItem()->setTitle('A4')->setIsPublished(false));

        $this->assertCount(2, $product->getItems());
        $this->assertCount(1, $product->getPublishedItems());
        $this->assertSame('A3', $product->getPublishedItems()->first()->getTitle());
    }
}
