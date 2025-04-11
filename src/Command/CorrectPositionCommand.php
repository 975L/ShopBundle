<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'shop:products:position',
    description: 'Reset position of Product/Items/Medias to a 5 gap',
)]
class CorrectPositionCommand extends Command
{
    public function __construct(
        private readonly ProductServiceInterface $productService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->positionProduct();

        $io->success('Medias position s have resetted.');

        return Command::SUCCESS;
    }

    private function positionProduct(): void
    {
        $products = $this->productService->findAll();
        $i = 5;
        foreach ($products as $product) {
            $product->setPosition($i);
            $i += 5;

            $this->position($product->getItems());
            $this->position($product->getMedias());

            $this->productService->save($product);
        }
    }

    // Position items given
    private function position($items): void
    {
        $i = 5;
        foreach ($items as $item) {
            $item->setPosition($i);
            $i += 5;
        }
    }
}