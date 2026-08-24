<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'c975l:shop:downloads:delete',
    description: 'Deletes download copies after their expiry date',
)]
class ProductItemDownloadDelete extends Command
{
    public function __construct(
        private readonly ProductItemDownloadServiceInterface $productItemDownloadService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->success(sprintf('%d expired download(s) deleted.', $this->productItemDownloadService->purgeExpired()));

        return Command::SUCCESS;
    }
}
