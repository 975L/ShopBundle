<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\LinkableRouteProvider;
use c975L\ShopBundle\Management\MenuProvider;
use c975L\ShopBundle\Management\ShopGuidedProjectProvider;
use c975L\ShopBundle\Management\ShopShortcutProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every CRUD controller and route this bundle's management providers name, checked against what its controllers actually declare - see ConfigBundle's ManagementTargetsTestCase
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider(),
            new LinkableRouteProvider($this->categoryRepository(), $this->createStub(TranslatorInterface::class)),
            // The guided projects generate their urls, so they take the recorders this test case reads them back from
            new ShopGuidedProjectProvider($this->adminUrlGenerator(), $this->configService(), $this->urlGenerator()),
            new ShopShortcutProvider($this->createStub(TranslatorInterface::class), $this->configService()),
        ];
    }

    // The roles the providers read are the site's own, which no configuration answers here - a stub returning one is enough for every target to be generated
    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return $configService;
    }

    // One category is enough to have the route its entries name checked too - an empty repository would leave the shop's index as the only linkable target
    private function categoryRepository(): ProductCategoryRepository
    {
        $repository = $this->createStub(ProductCategoryRepository::class);
        $repository->method('findAll')->willReturn([new ProductCategory()->setSlug('posters')->setName('Affiches')]);

        return $repository;
    }

    // This bundle's own controllers on top of ConfigBundle's: the public ones carry the routes its linkable entries name, the management ones those of its menus
    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller', __DIR__ . '/../../src/Controller/Management'];
    }
}
