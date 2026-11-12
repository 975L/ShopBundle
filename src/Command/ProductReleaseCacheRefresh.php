<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopBlockCacheInvalidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// A release date fires no Doctrine event: run right after midnight, this drops the cached product blocks on the day a product comes out, so a card, a button or an items table saying "not yet available" is cached again rather than rendered live while a release waits (see ShopBlockCacheTagProvider)
#[AsCommand(
    name: 'c975l:shop:releases:refresh',
    description: 'Drops the cached product blocks on the day a product comes out',
)]
class ProductReleaseCacheRefresh extends Command
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ShopBlockCacheInvalidator $cacheInvalidator,
    ) {
        parent::__construct();
    }

    // How far back a release is looked for: a worker down over a night or a weekend replays only its last missed run, and a release it skipped would otherwise stay "not yet available" in the cache for good
    private const int LOOKBACK_DAYS = 3;

    // Invalidates only around a day something comes out, the cache being left whole on every other night
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        if (!$this->productRepository->hasReleaseBetween($today->modify('-' . self::LOOKBACK_DAYS . ' days'), $today)) {
            $io->success('No product came out lately.');

            return Command::SUCCESS;
        }

        $this->cacheInvalidator->invalidateProducts();
        $io->success('A product came out lately: the cached product blocks were dropped.');

        return Command::SUCCESS;
    }
}
