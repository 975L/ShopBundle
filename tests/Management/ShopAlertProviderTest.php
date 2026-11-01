<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Management\ShopAlertProvider;
use c975L\ShopBundle\Repository\ProductItemRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A stock sold past its cap is said on the dashboard, a customer having paid for what it no longer holds
class ShopAlertProviderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $parameters = [];

    public function testNothingOversoldRaisesNothing(): void
    {
        $this->assertSame([], $this->provider(0)->getAlerts());
    }

    public function testAnOversoldStockIsAlerted(): void
    {
        $alerts = $this->provider(2)->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.items_oversold', $alerts[0]['label']);
        $this->assertSame('description.items_oversold', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
    }

    // The product list says nothing of which items these are: the alert does
    public function testTheItemsAreNamedWhicheverTitleTheyHold(): void
    {
        $this->provider(2, [['product' => 'Chaise', 'item' => 'Standard'], ['product' => 'Table', 'item' => null]])->getAlerts();

        $this->assertSame('Chaise / Standard, Table', $this->parameters['%items%']);
        $this->assertSame(2, $this->parameters['%count%']);
    }

    // Only so many fit on a dashboard line, and the count still says how many there are
    public function testMoreItemsThanAreNamedAreSaidToBeMore(): void
    {
        $this->provider(6, [['product' => 'Chaise', 'item' => null]])->getAlerts();

        $this->assertSame('Chaise, …', $this->parameters['%items%']);
    }

    /** @param list<array{product: ?string, item: ?string}> $titles */
    private function provider(int $oversold, array $titles = []): ShopAlertProvider
    {
        $repository = $this->createStub(ProductItemRepository::class);
        $repository->method('countOversold')->willReturn($oversold);
        $repository->method('findOversoldTitles')->willReturn($titles);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function (string $key, array $parameters = []): string {
            $this->parameters = [] === $parameters ? $this->parameters : $parameters;

            return $key;
        });

        $urlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/admin/product');

        return new ShopAlertProvider($repository, $this->createStub(ConfigServiceInterface::class), $urlGenerator, $translator);
    }
}
