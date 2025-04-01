<?php
namespace c975L\ShopBundle\Command;

use Twig\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use c975L\ShopBundle\Service\ProductServiceInterface;
use Symfony\Component\Console\Output\OutputInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

/**
 * Console command to create sitemap of pages, executed with 'pageedit:createSitemap'
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2017 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'shop:sitemaps:create',
    description: 'Creates the sitemap for the shop and products'
)]
class SitemapCreateCommand extends Command
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ProductServiceInterface $productService,
        private readonly Environment $environment

    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->createSitemap();

        $io->success('Sitemap created.');

        return Command::SUCCESS;
    }

    private function createSitemap(): void
    {
        $root = $this->configService->getContainerParameter('kernel.project_dir');
        $urlRoot = $this->configService->getParameter('c975LShop.sitemapBaseUrl');

        $products = $this->productService->findAll();
        $urls = [];
        foreach ($products as $product) {
            $urls[] = [
                'loc' => $urlRoot . '/shop/products/' . $product->getSlug(),
                'lastmod' => date('Y-m-d', strtotime($product->getModification()->format('Y-m-d H:i:s'))),
                'changefreq' => 'weekly',
                'priority' => 0.8,
            ];
        }

        //Writes file
        $sitemapContent = $this->environment->render('@c975LShop/sitemap.xml.twig', ['urls' => $urls]);
        $sitemapFile = $root . '/public/sitemap-shop.xml';
        file_put_contents($sitemapFile, $sitemapContent);
    }
}
