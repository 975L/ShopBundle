<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\ShopBundle\Controller\ShopController;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\ShopBundle\Service\ShopServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class ShopControllerTest extends TestCase
{
    // The terms of sales are served by whichever of the two is in charge, never by both - see the controller's own comment
    private function createController(array $bundles): ShopController
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<terms>');

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('parameter_bag', new ParameterBag(['kernel.bundles' => $bundles]));

        $controller = new ShopController(
            $this->createStub(ShopServiceInterface::class),
            $this->createStub(ShopSettingsRepository::class),
        );
        $controller->setContainer($container);

        return $controller;
    }

    // The index reads the single row for its own line, whose absence is the ordinary state of a shop that never opened the back-office screen - the template then prints the default sentence
    private function renderIndexWith(?ShopSettings $settings): array
    {
        $parameters = [];

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $name, array $context) use (&$parameters): string {
            $parameters = $context;

            return '<index>';
        });

        $settingsRepository = $this->createStub(ShopSettingsRepository::class);
        $settingsRepository->method('findSingle')->willReturn($settings);

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new ShopController($this->createStub(ShopServiceInterface::class), $settingsRepository);
        $controller->setContainer($container);
        $controller->index(new Request());

        return $parameters;
    }

    public function testIndexPassesTheIntroWrittenInTheBackOffice(): void
    {
        $settings = new ShopSettings();
        $settings->setIntro('Nos livres, à lire dès 3 ans.');

        $this->assertSame('Nos livres, à lire dès 3 ans.', $this->renderIndexWith($settings)['shopIntro']);
    }

    // Null and not an empty string: the template's "default" filter is what puts the shipped sentence back
    public function testIndexPassesNoIntroWhenTheShopHasNoSettingsRow(): void
    {
        $this->assertNull($this->renderIndexWith(null)['shopIntro']);
    }

    public function testTermsOfSalesRendersTheModelWhenSiteBundleIsAbsent(): void
    {
        $response = $this->createController(['c975LShopBundle' => 'c975L\ShopBundle\c975LShopBundle'])->termsOfSales();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<terms>', $response->getContent());
    }

    // No http cache header of its own any more, like every other public page of this bundle: what a page holds is cached per fragment and emptied when it changes, where an hour of max-age froze the whole page whatever happened to it
    public function testTermsOfSalesSetsNoCacheHeaderOfItsOwn(): void
    {
        $response = $this->createController([])->termsOfSales();

        $this->assertNull($response->getMaxAge());
    }

    // SiteBundle's default import creates the page holding the customizable block, which is then the only one to serve
    public function testTermsOfSalesIsNotFoundWhenSiteBundleIsInstalled(): void
    {
        $controller = $this->createController([
            'c975LShopBundle' => 'c975L\ShopBundle\c975LShopBundle',
            'c975LSiteBundle' => 'c975L\SiteBundle\c975LSiteBundle',
        ]);

        $this->expectException(NotFoundHttpException::class);

        $controller->termsOfSales();
    }
}
