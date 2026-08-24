<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests;

use c975L\ShopBundle\c975LShopBundle;
use c975L\ShopBundle\Service\ProductService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class c975LShopBundleTest extends TestCase
{
    public function testLoadExtensionImportsServicesYaml(): void
    {
        $container = new ContainerBuilder();

        new c975LShopBundle()->getContainerExtension()->load([], $container);

        $this->assertTrue($container->hasDefinition(ProductService::class));
    }

    public function testGetPathReturnsTheBundleRootDirectory(): void
    {
        $bundle = new c975LShopBundle();

        $this->assertSame(\dirname(__DIR__), $bundle->getPath());
    }
}
