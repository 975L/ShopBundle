<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\ProductAffinity;
use c975L\ShopBundle\Repository\ProductAffinityRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'c975l:shop:affinity:calculate',
    description: 'Calculates product affinity scores based on completed baskets (paid/shipped) - co-purchase analysis'
)]
class CalculateProductAffinityCommand extends Command
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ProductAffinityRepository $affinityRepository,
        private readonly ProductItemRepository $productItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopBlockCacheInvalidator $cacheInvalidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all affinity data before calculation')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Only analyze baskets from last N days', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = $input->getOption('reset');
        $days = $input->getOption('days');

        // Reset if requested
        if ($reset) {
            $this->entityManager->createQuery('DELETE FROM c975L\ShopBundle\Entity\ProductAffinity')->execute();
            $output->writeln('[RESET] Affinity data reset.');
        }

        // Fetch completed baskets (paid + shipped)
        $baskets = $this->fetchCompletedBaskets($days);
        $basketCount = count($baskets);

        if (0 === $basketCount) {
            $output->writeln('[INFO] No completed baskets found.');

            return Command::SUCCESS;
        }

        [$productPairs, $productTotalOrders] = $this->analyseBaskets($baskets);
        $pairCount = count($productPairs);
        $processed = $this->saveAffinities($productPairs, $productTotalOrders);

        $this->entityManager->flush();

        // The recommendations blocks read those scores at render time, and neither the bulk DELETE above nor a run finding no pair at all fires the Doctrine event ShopCacheInvalidationListener listens on
        $this->cacheInvalidator->invalidateProducts();

        // Summary
        $output->writeln(sprintf('[SUCCESS] Analyzed %d baskets, processed %d pairs, updated %d records', $basketCount, $pairCount, $processed));

        return Command::SUCCESS;
    }

    // Analyze baskets and extract product pairs, along with how many orders each product took part in
    // @return array{0: array<string, array{product1: mixed, product2: mixed, count: int}>, 1: array<mixed, int>}
    private function analyseBaskets(array $baskets): array
    {
        $productPairs = [];
        $productTotalOrders = [];

        foreach ($baskets as $basket) {
            $productIds = $this->getProductIdsFromBasket($basket);

            if (count($productIds) < 2) {
                continue;
            }

            // Track total orders per product
            foreach ($productIds as $productId) {
                $productTotalOrders[$productId] = ($productTotalOrders[$productId] ?? 0) + 1;
            }

            // Generate all pairs (combinations) from this basket
            foreach ($this->generateProductPairs($productIds) as $pair) {
                $key = $this->getPairKey($pair[0], $pair[1]);
                $productPairs[$key] = [
                    'product1' => $pair[0],
                    'product2' => $pair[1],
                    'count' => ($productPairs[$key]['count'] ?? 0) + 1,
                ];
            }
        }

        return [$productPairs, $productTotalOrders];
    }

    // Save or update ProductAffinity entities, answering how many were written
    private function saveAffinities(array $productPairs, array $productTotalOrders): int
    {
        $now = new \DateTime();
        $processed = 0;

        foreach ($productPairs as $pairData) {
            // Calculate affinity score (0-100)
            $totalOrders = $productTotalOrders[$pairData['product1']] ?? 1;
            $affinityScore = min(100, ($pairData['count'] / $totalOrders) * 100);

            $affinity = $this->findOrCreateAffinity($pairData['product1'], $pairData['product2']);
            $affinity->setCoPurchaseCount($pairData['count']);
            $affinity->setAffinityScore(round($affinityScore, 2));
            $affinity->setLastCalculated($now);

            $this->entityManager->persist($affinity);

            ++$processed;
            if (0 === $processed % 50) {
                $this->entityManager->flush();
            }
        }

        return $processed;
    }

    // Find or create ProductAffinity entity
    private function findOrCreateAffinity(mixed $product1Id, mixed $product2Id): ProductAffinity
    {
        $affinity = $this->affinityRepository->findOneBy([
            'product1' => $product1Id,
            'product2' => $product2Id,
        ]);

        if ($affinity instanceof ProductAffinity) {
            return $affinity;
        }

        $affinity = new ProductAffinity();
        $affinity->setProduct1($this->entityManager->getReference('c975L\ShopBundle\Entity\Product', $product1Id));
        $affinity->setProduct2($this->entityManager->getReference('c975L\ShopBundle\Entity\Product', $product2Id));

        return $affinity;
    }

    // Fetches completed baskets (status: 'paid' or 'shipped').
    private function fetchCompletedBaskets(?int $days): array
    {
        $qb = $this->basketRepository->createQueryBuilder('b')
            ->where("b.status IN ('paid', 'shipped')");

        if (null !== $days) {
            $since = new \DateTime(sprintf('-%d days', $days));
            $qb->andWhere('b.creation >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    private function getProductIdsFromBasket($basket): array
    {
        // Only get 'product' items, NOT 'crowdfunding', 'lottery', etc.
        // The line's key is the ProductItem id, which is all this reads of the basket
        $productItemIds = array_keys($basket->getItems()['product'] ?? []);

        if (empty($productItemIds)) {
            return [];
        }

        // Get ProductItem entities and extract unique Product IDs
        $productItems = $this->productItemRepository->findBy(['id' => $productItemIds]);
        $productIds = [];

        foreach ($productItems as $productItem) {
            $product = $productItem->getProduct();
            // Ensure ProductItem has a valid Product (not null, not Crowdfunding)
            if (null !== $product) {
                $productIds[] = $product->getId();
            }
        }

        return array_unique($productIds);
    }

    private function generateProductPairs(array $productIds): array
    {
        $pairs = [];
        $count = count($productIds);

        for ($i = 0; $i < $count - 1; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                // Always store pairs with smaller ID first for consistency
                $pairs[] = [
                    min($productIds[$i], $productIds[$j]),
                    max($productIds[$i], $productIds[$j]),
                ];
            }
        }

        return $pairs;
    }

    private function getPairKey(int $product1Id, int $product2Id): string
    {
        // Ensure consistent ordering (smaller ID first)
        $min = min($product1Id, $product2Id);
        $max = max($product1Id, $product2Id);

        return "{$min}_{$max}";
    }
}
