<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Listener;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Listener\ProductItemListener;
use c975L\ShopBundle\Listener\ProductListener;
use c975L\ShopBundle\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

// Both listeners stamp a creation date on persist, and both have to leave alone the one an entity already carries - a frozen demo dataset, an imported catalog
class ProductCreationDateTest extends TestCase
{
    public function testAProductKeepsTheCreationDateItCarries(): void
    {
        $product = new Product()->setCreation(new \DateTime('2026-01-12'));

        $this->productListener()->prePersist($product, $this->args($product));

        $this->assertSame('2026-01-12', $product->getCreation()?->format('Y-m-d'));
    }

    public function testAProductWithoutOneIsStamped(): void
    {
        $product = new Product();

        $this->productListener()->prePersist($product, $this->args($product));

        $this->assertNotNull($product->getCreation());
    }

    public function testAnItemKeepsTheCreationDateItCarries(): void
    {
        $item = new ProductItem()->setCreation(new \DateTime('2026-01-12'));

        $this->itemListener()->prePersist($item, $this->args($item));

        $this->assertSame('2026-01-12', $item->getCreation()?->format('Y-m-d'));
    }

    public function testAnItemWithoutOneIsStamped(): void
    {
        $item = new ProductItem();

        $this->itemListener()->prePersist($item, $this->args($item));

        $this->assertNotNull($item->getCreation());
    }

    private function productListener(): ProductListener
    {
        return new ProductListener(
            $this->createStub(Security::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ProductRepository::class),
        );
    }

    private function itemListener(): ProductItemListener
    {
        return new ProductItemListener(
            $this->createStub(Security::class),
            $this->createStub(EntityManagerInterface::class),
            new AsciiSlugger(),
        );
    }

    private function args(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }
}
