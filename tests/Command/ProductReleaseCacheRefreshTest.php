<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Command;

use c975L\ShopBundle\Command\ProductReleaseCacheRefresh;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// A release date fires no event: the nightly run drops the cached product blocks on the day a product comes out, and on that day only
class ProductReleaseCacheRefreshTest extends TestCase
{
    // A product out today: the cards, buttons and items tables cached the day before are dropped
    public function testAReleaseTodayDropsTheProductBlocks(): void
    {
        $invalidator = $this->createMock(ShopBlockCacheInvalidator::class);
        $invalidator->expects($this->once())->method('invalidateProducts');

        $this->assertSame(Command::SUCCESS, $this->refresh(true, $invalidator));
    }

    // Nothing out today: the cache is left whole
    public function testNoReleaseLeavesTheCache(): void
    {
        $invalidator = $this->createMock(ShopBlockCacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidateProducts');

        $this->assertSame(Command::SUCCESS, $this->refresh(false, $invalidator));
    }

    // Runs the command over a catalog that does, or does not, release something today
    private function refresh(bool $releaseToday, ShopBlockCacheInvalidator $invalidator): int
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('hasReleaseBetween')->willReturn($releaseToday);

        return new CommandTester(new ProductReleaseCacheRefresh($repository, $invalidator))->execute([]);
    }
}
