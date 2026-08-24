<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use c975L\ShopBundle\Service\ProductItemStockAlertServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'c975l:shop:stock-alerts:send',
    description: 'Tells the visitors waiting on an item that it is back in stock',
)]
class ProductItemStockAlertSend extends Command
{
    // How many alerts one run sends at most. A restocked best-seller can carry thousands of subscriptions, and sending them in one pass would hold the mailer for as long as it takes; the hourly run walks the queue instead
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ProductItemStockAlertServiceInterface $stockAlertService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'How many alerts to send in this run', self::DEFAULT_LIMIT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        if ($limit < 1) {
            $io->error('The limit must be at least 1.');

            return Command::INVALID;
        }

        $sent = $this->stockAlertService->notifyPending($limit);
        $pending = $this->stockAlertService->countPending();

        // What is left waiting is said every run: a queue that stops going down is how a shop finds out its mailer is refusing, where a bare success line would hide it
        $io->success(sprintf('%d stock alert(s) sent, %d still waiting.', $sent, $pending));

        return Command::SUCCESS;
    }
}
