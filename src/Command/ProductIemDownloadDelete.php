<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use DateTimeImmutable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'shop:downloads:delete',
    description: 'Deletes download files after their expiry date',
)]
class ProductIemDownloadDelete extends Command
{
    private string $downloadDir;

    public function __construct(
        private readonly ProductItemDownloadRepository $downloadRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
        $this->downloadDir = $this->parameterBag->get('kernel.project_dir') . '/public/downloads/';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expirationDate = new DateTimeImmutable();
        $expiredDownloads = $this->downloadRepository->findExpired($expirationDate);

        foreach ($expiredDownloads as $download) {
            $filePath = $this->downloadDir . $download->getFilename();
            if ($this->filesystem->exists($filePath)) {
                $this->filesystem->remove($filePath);
            }
        }

        $io->success('Unused medias deleted.');

        return Command::SUCCESS;
    }
}