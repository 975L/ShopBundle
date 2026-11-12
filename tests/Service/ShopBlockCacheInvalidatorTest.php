<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The shop's tags are dropped once what changed is committed: at once outside a transaction, held while one is open
class ShopBlockCacheInvalidatorTest extends TestCase
{
    /** @var list<list<string>> */
    private array $invalidated = [];

    private bool $inTransaction = false;

    // Outside a transaction (a reorder's statements, a refund's), the tags are dropped at once
    public function testOutsideATransactionTheTagsAreDroppedAtOnce(): void
    {
        $this->invalidator()->invalidateProducts();

        $this->assertSame([ShopBlockCacheInvalidator::PRODUCT_TAGS], $this->invalidated);
    }

    // Inside one, nothing is dropped, not even by a flush within it, until the transaction is over
    public function testInsideATransactionTheTagsWaitForItsEnd(): void
    {
        $this->inTransaction = true;
        $invalidator = $this->invalidator();

        $invalidator->invalidateProducts();
        $invalidator->invalidateCategories();
        $invalidator->postFlush();
        $this->assertSame([], $this->invalidated);

        $this->inTransaction = false;
        $invalidator->postFlush();
        $this->assertSame([[ShopBlockCacheInvalidator::CACHE_TAG_PRODUCTS, ShopBlockCacheInvalidator::CACHE_TAG_CATEGORIES]], $this->invalidated);
    }

    // The end of the request, the command or the message drops what is still held, once
    public function testTheEndOfTheRequestDropsWhatIsHeld(): void
    {
        $this->inTransaction = true;
        $invalidator = $this->invalidator();
        $invalidator->invalidateProducts();

        $invalidator->drain();
        $invalidator->drain();

        $this->assertSame([ShopBlockCacheInvalidator::PRODUCT_TAGS], $this->invalidated);
    }

    // An invalidator on a cache recording what it is told to drop, on a connection whose transaction the test opens and closes
    private function invalidator(): ShopBlockCacheInvalidator
    {
        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('invalidateTags')->willReturnCallback(function (array $tags): bool {
            $this->invalidated[] = $tags;

            return true;
        });

        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => $this->inTransaction);

        return new ShopBlockCacheInvalidator($cache, $connection);
    }
}
