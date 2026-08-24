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
use c975L\ShopBundle\Entity\ProductAffinity;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Listener\ShopCacheInvalidationListener;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Which change of the catalog drops which cached blocks - UiBundle only ever invalidates the Block that was edited, and knows nothing of what these kinds query at render time
class ShopCacheInvalidationListenerTest extends TestCase
{
    private array $invalidated;

    protected function setUp(): void
    {
        $this->invalidated = [];
    }

    public function testAProductChangeDropsTheProductBlocks(): void
    {
        $this->listen(new Product());

        $this->assertSame([[ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS]], $this->invalidated);
    }

    // The price, the stock and the formats a card prints are all read off the items
    public function testAnItemChangeDropsTheProductBlocks(): void
    {
        $this->listen(new ProductItem());

        $this->assertSame([[ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS]], $this->invalidated);
    }

    // Every Media of this bundle, and not the product's own picture alone: the items table prints an item's picture with its alternative text, and reads the format badge off the name of the file the item is bought for
    public function testAnyPictureOrFileChangeDropsTheProductBlocks(): void
    {
        foreach ([new ProductMedia(), new ProductItemMedia(), new ProductItemFile()] as $media) {
            $this->invalidated = [];
            $this->listen($media);

            $this->assertSame([[ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS]], $this->invalidated, $media::class);
        }
    }

    // Recomputed by c975l:shop:affinity:calculate, which is what the recommendations block reads
    public function testAnAffinityChangeDropsTheProductBlocks(): void
    {
        $this->listen(new ProductAffinity());

        $this->assertSame([[ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS]], $this->invalidated);
    }

    // A category renamed changes its own listing and what a products block pointing at it shows, so both tags go
    public function testACategoryChangeDropsBothTags(): void
    {
        $this->listen(new ProductCategory());

        $this->assertSame(
            [[ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES, ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS]],
            $this->invalidated
        );
    }

    // Every entity of the site travels through these events, and the catalog is not concerned by most of them
    public function testAnEntityOfAnotherBundleDropsNothing(): void
    {
        $this->listen(new Block());

        $this->assertSame([], $this->invalidated);
    }

    // A brand new item on an already-cached product is an INSERT, for which postUpdate never fires
    public function testTheThreeEventsAllInvalidate(): void
    {
        $listener = $this->createListener();
        $manager = $this->createStub(EntityManagerInterface::class);
        $product = new Product();

        $listener->postPersist(new PostPersistEventArgs($product, $manager));
        $listener->postUpdate(new PostUpdateEventArgs($product, $manager));
        $listener->preRemove(new PreRemoveEventArgs($product, $manager));

        $this->assertCount(3, $this->invalidated);
    }

    private function listen(object $entity): void
    {
        $this->createListener()->postUpdate(new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class)));
    }

    private function createListener(): ShopCacheInvalidationListener
    {
        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('invalidateTags')->willReturnCallback(function (array $tags): bool {
            $this->invalidated[] = $tags;

            return true;
        });

        return new ShopCacheInvalidationListener(new ShopBlockCacheInvalidator($cache));
    }
}
