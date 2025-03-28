<?php

namespace c975L\ShopBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use c975L\ShopBundle\Service\ProductItemServiceInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'shop:media:delete',
    description: 'Deletes unused medias from c975L/ShopBundle and updates database for inexisting ones',
)]
class MediaDeleteCommand extends Command
{
    public function __construct(
        private readonly ProductServiceInterface $productService,
        private readonly ProductItemServiceInterface $productItemService,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $publicDir = $this->parameterBag->get('kernel.project_dir') . '/public/';
        $privateDir = $this->parameterBag->get('kernel.project_dir') . '/private/';

        // Adds medias from database
        $productMedias = $this->productService->findAllMedias();
        $productItemMedias = $this->productItemService->findAllMedias();
        $mediasDb = [];
        $medias = array_merge($productMedias, $productItemMedias);
        foreach($medias as $media) {
            if (null !== $media->getName()) {
                $mediasDb[] = $publicDir . $media->getName();
            }
        }

        // Adds files from database
        $productItemFiles = $this->productItemService->findAllFiles();
        foreach($productItemFiles as $item) {
            if (null !== $item->getName()) {
                $mediasDb[] = $privateDir . $item->getName();
            }
        }

        // Gets medias from shop directory
        $mediasFiles = [];

        $finder = new Finder();
        $finder
            ->files()
            ->in($publicDir . 'medias/shop/')
            ->in($privateDir . 'medias/shop/')
            ->depth('1');
        ;

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $mediasFiles[] = $file->getRealPath();
            }
        }

        // Suppress unused medias
        $unusedFiles = array_diff($mediasFiles, $mediasDb);
        if (count($unusedFiles) > 0) {
            $io->title('Unused medias:');
            $io->listing($unusedFiles);

            foreach ($unusedFiles as $file) {
                unlink($file);
            }
        }

        // Updates db from inexisting files, only for ProductItem (which should not be the case)
        $inexistingFiles = array_diff($mediasDb, $mediasFiles);
        if (count($inexistingFiles) > 0) {
            $io->title('Inexisting files:');
            $io->listing($inexistingFiles);

            foreach ($inexistingFiles as $file) {
                $filePath = str_replace($publicDir, '', $file);
                if (strpos($filePath, 'shop/items/') !== false) {
                    $this->productItemService->deleteOneMediaByName($filePath);
                }
            }
        }

        $io->success('Unused medias deleted.');

        return Command::SUCCESS;
    }
}
