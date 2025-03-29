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
    private string $publicDir;
    private string $privateDir;

    public function __construct(
        private readonly ProductServiceInterface $productService,
        private readonly ProductItemServiceInterface $productItemService,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
        $this->publicDir = $this->parameterBag->get('kernel.project_dir') . '/public/';
        $this->privateDir = $this->parameterBag->get('kernel.project_dir') . '/private/';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mediasDb = $this->collectMediasFromDatabase();
        $mediasFiles = $this->collectMediasFromFilesystem();

        $this->removeUnusedFiles($mediasFiles, $mediasDb, $io);
        $this->updateDatabaseForMissingFiles($mediasDb, $mediasFiles, $io);

        $io->success('Unused medias deleted.');

        return Command::SUCCESS;
    }

    // Gets the list of media files from the database
    private function collectMediasFromDatabase(): array
    {
        $mediasDb = [];

        // Adds the media files of products
        $productMedias = $this->productService->findAllMedias();
        $productItemMedias = $this->productItemService->findAllMedias();

        $medias = array_merge($productMedias, $productItemMedias);
        foreach($medias as $media) {
            if (null !== $media->getName()) {
                $mediasDb[] = $this->publicDir . $media->getName();
            }
        }

        // Adds the files of products
        $productItemFiles = $this->productItemService->findAllFiles();
        foreach($productItemFiles as $item) {
            if (null !== $item->getName()) {
                $mediasDb[] = $this->privateDir . $item->getName();
            }
        }

        return $mediasDb;
    }

    // Gets the list of media files from the filesystem
    private function collectMediasFromFilesystem(): array
    {
        $mediasFiles = [];

        $finder = new Finder();
        $finder
            ->files()
            ->in($this->publicDir . 'medias/shop/')
            ->in($this->privateDir . 'medias/shop/')
            ->depth('1');

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $mediasFiles[] = $file->getRealPath();
            }
        }

        return $mediasFiles;
    }

    // Suppress the files that are not referenced in the database
    private function removeUnusedFiles(array $mediasFiles, array $mediasDb, SymfonyStyle $io): void
    {
        $unusedFiles = array_diff($mediasFiles, $mediasDb);

        if (count($unusedFiles) > 0) {
            $io->title('Unused medias:');
            $io->listing($unusedFiles);

            foreach ($unusedFiles as $file) {
                unlink($file);
            }
        }
    }

    // Updates the database for files that are not referenced in the filesystem
    private function updateDatabaseForMissingFiles(array $mediasDb, array $mediasFiles, SymfonyStyle $io): void
    {
        $inexistingFiles = array_diff($mediasDb, $mediasFiles);

        if (count($inexistingFiles) > 0) {
            $io->title('Inexisting files:');
            $io->listing($inexistingFiles);

            foreach ($inexistingFiles as $file) {
                $filePath = str_replace($this->publicDir, '', $file);
                if (strpos($filePath, 'shop/items/') !== false) {
                    $this->productItemService->deleteOneMediaByName($filePath);
                }
            }
        }
    }
}