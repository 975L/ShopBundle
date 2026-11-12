<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Listener;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Listener\ProductItemListener;
use c975L\ShopBundle\Listener\ProductListener;
use c975L\ShopBundle\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// The catalog's listeners run on a write and on a real edit only: a visitor's flush (a basket, a download, a stock alert) leaves the products it did not change alone, author and date included
class ProductListenersOnUpdateTest extends TestCase
{
    // A sale counting the stock dates the item, and changes neither its author nor anything else
    public function testASaleChangesNeitherTheAuthorNorTheDate(): void
    {
        $editor = $this->createStub(UserInterface::class);
        $item = new ProductItem()->setTitle('item-1')->setSlug('item-1')->setUser($editor)->setPosition(5);
        $item->setModification(new \DateTime('2026-01-12'));

        $this->itemListener($this->createStub(UserInterface::class))->preUpdate($item, $this->updateArgs($item, ['orderedQuantity' => [1, 2]]));

        $this->assertSame($editor, $item->getUser());
        $this->assertSame('2026-01-12', $item->getModification()?->format('Y-m-d'));
    }

    // An editor's change dates and signs the item, its slug kept so its anchor and its archive row stay
    public function testAnEditDatesAndSignsTheItemAndKeepsItsSlug(): void
    {
        $editor = $this->createStub(UserInterface::class);
        $item = new ProductItem()->setTitle('Renamed')->setSlug('item-1')->setPosition(5);
        $item->setModification(new \DateTime('2026-01-12'));

        $this->itemListener($editor)->preUpdate($item, $this->updateArgs($item, ['title' => ['item-1', 'Renamed']]));

        $this->assertSame($editor, $item->getUser());
        $this->assertSame('item-1', $item->getSlug());
        $this->assertNotSame('2026-01-12', $item->getModification()?->format('Y-m-d'));
    }

    // A new item, or one an archive names no slug for, is named off its title
    public function testANewItemIsNamedOffItsTitle(): void
    {
        $item = new ProductItem()->setTitle('Poster A3')->setSlug('');
        new Product()->addItem($item);

        $this->itemListener(null)->prePersist($item, new PrePersistEventArgs($item, $this->createStub(EntityManagerInterface::class)));

        $this->assertSame('poster-a3', $item->getSlug());
        $this->assertNotNull($item->getModification());
    }

    // A product edited is dated and signed by the account editing it
    public function testAnEditedProductIsDatedAndSigned(): void
    {
        $editor = $this->createStub(UserInterface::class);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($editor);
        $product = new Product()->setTitle('catalog')->setPosition(5);

        new ProductListener($security, $this->createStub(ProductRepository::class))->preUpdate($product, $this->updateArgs($product, ['title' => ['old', 'catalog']]));

        $this->assertSame($editor, $product->getUser());
        $this->assertNotNull($product->getModification());
    }

    private function itemListener(?UserInterface $user): ProductItemListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new ProductItemListener($security);
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    private function updateArgs(object $entity, array $changeSet): PreUpdateEventArgs
    {
        return new PreUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class), $changeSet);
    }
}
