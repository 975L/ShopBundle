<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\ProductCategoryExportProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Service\ProductUpdater;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What saving a category on the back office leaves behind at its old url
class ProductCategoryCrudControllerTest extends TestCase
{
    // A saved category goes through the updater, which redirects a changed slug, before the flush
    public function testAnEditGoesThroughTheUpdater(): void
    {
        $category = new ProductCategory()->setName('catalog')->setSlug('catalog');
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->once())->method('updateCategory')->with($category);

        $this->createController($updater)->updateEntity($this->createStub(EntityManagerInterface::class), $category);
    }

    // The controller with its collaborators stubbed, the updater given
    private function createController(ProductUpdater $updater): ProductCategoryCrudController
    {
        return new ProductCategoryCrudController(
            $this->createStub(AdminContextProviderInterface::class),
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(Connection::class),
            $this->createStub(ContentExporter::class),
            $this->createStub(ContentLocaleScreen::class),
            $this->createStub(ProductCategoryExportProvider::class),
            $this->createStub(ProductCategoryRepository::class),
            $updater,
            $this->createStub(ShopTranslator::class),
            $this->createStub(TableExporter::class),
            $this->createStub(TranslatorInterface::class),
        );
    }
}
