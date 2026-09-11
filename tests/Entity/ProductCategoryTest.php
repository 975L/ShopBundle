<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Entity;

use c975L\ShopBundle\Entity\ProductCategory;
use PHPUnit\Framework\TestCase;

// What a category's getters read once a language is laid over it
class ProductCategoryTest extends TestCase
{
    // The translated name is read, an untranslated description falls back on the text it was written with, which getUntranslated() always gives
    public function testATranslationIsLaidOverTheCategoryTexts(): void
    {
        $category = new ProductCategory()->setName('Tables')->setDescription('En bois massif');
        $category->setTranslated(['name' => 'Tables EN']);

        $this->assertSame('Tables EN', $category->getName());
        $this->assertSame('En bois massif', $category->getDescription());
        $this->assertSame('Tables', $category->getUntranslated('name'));
        $this->assertNull($category->getUntranslated('slug'));
    }
}
