<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use c975L\ShopBundle\Entity\ProductAffinity;
use c975L\ShopBundle\Repository\BasketRepository;
use c975L\ShopBundle\Repository\ProductAffinityRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'shop:affinity:calculate',
    description: 'Calculates product affinity scores based on completed baskets (paid/shipped) - co-purchase analysis'
)]
class CalculateProductAffinityCommand extends Command
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ProductAffinityRepository $affinityRepository,
        private readonly ProductItemRepository $productItemRepository,
        private readonly EntityManagerInterface $entityManager
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

        if ($basketCount === 0) {
            $output->writeln('[INFO] No completed baskets found.');
            return Command::SUCCESS;
        }

        // Analyze baskets and extract product pairs
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
            $pairs = $this->generateProductPairs($productIds);

            foreach ($pairs as $pair) {
                $key = $this->getPairKey($pair[0], $pair[1]);
                $productPairs[$key] = [
                    'product1' => $pair[0],
                    'product2' => $pair[1],
                    'count' => ($productPairs[$key]['count'] ?? 0) + 1
                ];
            }
        }

        $pairCount = count($productPairs);

        // Save or update ProductAffinity entities
        $now = new \DateTime();
        $processed = 0;

        foreach ($productPairs as $pairData) {
            $product1Id = $pairData['product1'];
            $product2Id = $pairData['product2'];
            $coPurchaseCount = $pairData['count'];

            // Calculate affinity score (0-100)
            $totalOrders = $productTotalOrders[$product1Id] ?? 1;
            $affinityScore = min(100, ($coPurchaseCount / $totalOrders) * 100);

            // Find or create ProductAffinity entity
            $affinity = $this->affinityRepository->findOneBy([
                'product1' => $product1Id,
                'product2' => $product2Id
            ]);

            if (!$affinity) {
                $affinity = new ProductAffinity();
                $product1 = $this->entityManager->getReference('c975L\ShopBundle\Entity\Product', $product1Id);
                $product2 = $this->entityManager->getReference('c975L\ShopBundle\Entity\Product', $product2Id);
                $affinity->setProduct1($product1);
                $affinity->setProduct2($product2);
            }

            $affinity->setCoPurchaseCount($coPurchaseCount);
            $affinity->setAffinityScore(round($affinityScore, 2));
            $affinity->setLastCalculated($now);

            $this->entityManager->persist($affinity);

            $processed++;
            if ($processed % 50 === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        // Summary
        $output->writeln(sprintf('[SUCCESS] Analyzed %d baskets, processed %d pairs, updated %d records', $basketCount, $pairCount, $processed));

        return Command::SUCCESS;
    }

    /**
     * Fetches completed baskets (status: 'paid' or 'shipped')
     */
    private function fetchCompletedBaskets(?int $days): array
    {
        $qb = $this->basketRepository->createQueryBuilder('b')
            ->where("b.status IN ('paid', 'shipped')");

        if ($days !== null) {
            $since = new \DateTime(sprintf('-%d days', $days));
            $qb->andWhere('b.creation >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    private function getProductIdsFromBasket($basket): array
    {
        $productItemIds = [];
        // Only get 'product' items, NOT 'crowdfunding', 'lottery', etc.
        $items = $basket->getItems()['product'] ?? [];

        foreach ($items as $id => $item) {
            $productItemIds[] = $id;
        }

        if (empty($productItemIds)) {
            return [];
        }

        // Get ProductItem entities and extract unique Product IDs
        $productItems = $this->productItemRepository->findBy(['id' => $productItemIds]);
        $productIds = [];

        foreach ($productItems as $productItem) {
            $product = $productItem->getProduct();
            // Ensure ProductItem has a valid Product (not null, not Crowdfunding)
            if ($product !== null) {
                $productIds[] = $product->getId();
            }
        }

        return array_unique($productIds);
    }

    private function generateProductPairs(array $productIds): array
    {
        $pairs = [];
        $count = count($productIds);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                // Always store pairs with smaller ID first for consistency
                $pairs[] = [
                    min($productIds[$i], $productIds[$j]),
                    max($productIds[$i], $productIds[$j])
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