<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

// What saving an edited product leaves behind at its old url, the back office and the agents sharing it
class ProductUpdaterTest extends TestCase
{
    private array $persisted = [];

    private array $removed = [];

    // A changed slug sends the old url to the new one, and the edit is dated
    public function testAChangedSlugIsRedirected(): void
    {
        $product = new Product()->setSlug('item-2');

        $this->updater(originalSlug: 'item-1')->update($product);

        $this->assertNotNull($product->getModification());
        $this->assertCount(1, $this->persisted);
        $this->assertSame(['/shop/products/item-1', '/shop/products/item-2', true], [$this->persisted[0]->getFromPath(), $this->persisted[0]->getToUrl(), $this->persisted[0]->isPermanent()]);
    }

    // A slug kept writes no redirect
    public function testAKeptSlugIsNotRedirected(): void
    {
        $this->updater(originalSlug: 'item-1')->update(new Product()->setSlug('item-1'));

        $this->assertSame([], $this->persisted);
    }

    // Renaming a product back to a name it already had would otherwise leave the two rows pointing at each other
    public function testRenamingBackRemovesTheRedirectThatWouldLoop(): void
    {
        $reverse = new Redirect()->setFromPath('/shop/products/item-1')->setToUrl('/shop/products/item-2');

        $this->updater(['/shop/products/item-1' => $reverse], 'item-2')->update(new Product()->setSlug('item-1'));

        $this->assertSame([$reverse], $this->removed);
        $this->assertSame(['/shop/products/item-2', '/shop/products/item-1'], [$this->persisted[0]->getFromPath(), $this->persisted[0]->getToUrl()]);
    }

    // The updater over redirects held by their source path, the entity manager keeping what it is handed
    private function updater(array $byFromPath = [], ?string $originalSlug = null): ProductUpdater
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturnCallback(static fn (string $path): ?Redirect => $byFromPath[$path] ?? null);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(null === $originalSlug ? [] : ['slug' => $originalSlug]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });

        return new ProductUpdater($entityManager, $repository);
    }
}
