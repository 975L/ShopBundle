<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Command;

use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Command\CalculateProductAffinityCommand;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductAffinity;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductAffinityRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// What settled orders say about the articles people buy together: the pairs the command reads out of them, and the score it files each under
class CalculateProductAffinityCommandTest extends TestCase
{
    /** @var list<ProductAffinity> */
    private array $persisted = [];

    // Two articles bought in the same order are what an affinity is, and the pair is filed once whichever way round the basket listed them
    public function testTwoArticlesBoughtTogetherAreFiledAsOnePair(): void
    {
        $tester = $this->calculate([
            $this->basket([1, 2]),
            // The same two, listed the other way round: the pair is normalised on the smaller id, so this is the very same one
            $this->basket([2, 1]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertCount(1, $this->persisted);
        $this->assertSame(2, $this->persisted[0]->getCoPurchaseCount());
    }

    // An order holding one article says nothing about what goes with what
    public function testAnOrderOfASingleArticleYieldsNoPair(): void
    {
        $tester = $this->calculate([$this->basket([1])]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([], $this->persisted);
    }

    // Three articles in one order make the three pairs they can form, not one
    public function testThreeArticlesInOneOrderMakeThreePairs(): void
    {
        $this->calculate([$this->basket([1, 2, 3])]);

        $this->assertCount(3, $this->persisted);
    }

    // The score is what share of a product's orders the other one shares: bought together every time, they score 100
    public function testTwoArticlesAlwaysBoughtTogetherScoreFull(): void
    {
        $this->calculate([$this->basket([1, 2]), $this->basket([1, 2])]);

        $this->assertSame(100.0, $this->persisted[0]->getAffinityScore());
    }

    // Bought together once out of two orders, the pair is worth half of them
    public function testAPairSharingHalfTheOrdersScoresHalf(): void
    {
        $this->calculate([$this->basket([1, 2]), $this->basket([1, 3])]);

        $scores = array_map(static fn (ProductAffinity $affinity): float => $affinity->getAffinityScore(), $this->persisted);
        $this->assertSame([50.0, 50.0], $scores);
    }

    // Nothing settled yet, nothing to say - and the command leaves without touching the table
    public function testNoCompletedOrderWritesNothing(): void
    {
        $tester = $this->calculate([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No completed baskets found', $tester->getDisplay());
        $this->assertSame([], $this->persisted);
    }

    // A basket carrying only crowdfunding or lottery lines holds no article this command counts
    public function testABasketHoldingNoProductLineIsIgnored(): void
    {
        $basket = new Basket();
        $basket->setItems(['lottery' => [1 => ['quantity' => 1]]]);

        $this->calculate([$basket]);

        $this->assertSame([], $this->persisted);
    }

    // One basket, its "product" lines keyed by ProductItem id exactly as BasketService writes them
    private function basket(array $productIds): Basket
    {
        $basket = new Basket();
        $basket->setItems(['product' => array_fill_keys($productIds, ['quantity' => 1])]);

        return $basket;
    }

    /** @param list<Basket> $baskets */
    private function calculate(array $baskets): CommandTester
    {
        $this->persisted = [];

        $command = new CalculateProductAffinityCommand(
            $this->basketRepository($baskets),
            $this->affinityRepository(),
            $this->productItemRepository(),
            $this->entityManager(),
            $this->createStub(ShopBlockCacheInvalidator::class),
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    /** @param list<Basket> $baskets */
    private function basketRepository(array $baskets): BasketRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($baskets);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createStub(BasketRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $repository;
    }

    // Every ProductItem asked for stands for the product of the same id, which is all this command reads of it
    private function productItemRepository(): ProductItemRepository
    {
        $repository = $this->createStub(ProductItemRepository::class);
        $repository->method('findBy')->willReturnCallback(static function (array $criteria): array {
            $items = [];

            foreach ($criteria['id'] as $id) {
                $product = new Product();
                $reflection = new \ReflectionProperty(Product::class, 'id');
                $reflection->setValue($product, $id);

                $item = new ProductItem();
                $item->setProduct($product);
                $items[] = $item;
            }

            return $items;
        });

        return $repository;
    }

    // Nothing on file yet: every pair the run finds is a new row
    private function affinityRepository(): ProductAffinityRepository
    {
        $repository = $this->createStub(ProductAffinityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $emptyQuery = $this->createStub(Query::class);
        $emptyQuery->method('execute')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($emptyQuery);
        $em->method('getReference')->willReturnCallback(static fn (string $class, mixed $id): object => new Product());
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof ProductAffinity) {
                $this->persisted[] = $entity;
            }
        });

        return $em;
    }
}
