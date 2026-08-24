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
use c975L\ShopBundle\Management\ShopShortcutProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The dashboard tile says what pressing it does, not what the shop currently is - a tile reading "enable" on a shop already in test mode would take it out of it
class ShopShortcutProviderTest extends TestCase
{
    public function testTheTileOffersToEnableWhenTheShopSellsForReal(): void
    {
        $shortcut = $this->shortcut(false);

        $this->assertSame('label.shop_test_mode_enable', $shortcut['label']);
        $this->assertFalse($shortcut['active']);
    }

    public function testTheTileOffersToDisableWhenTheShopIsInTestMode(): void
    {
        $shortcut = $this->shortcut(true);

        $this->assertSame('label.shop_test_mode_disable', $shortcut['label']);
        $this->assertTrue($shortcut['active']);
    }

    private function shortcut(bool $enabled): array
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn (string $slug) => 'shop-test-mode' === $slug ? $enabled : 'ROLE_ADMIN');

        // The stub hands the key back untranslated, which is what the assertions read
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ShopShortcutProvider($translator, $configService)->getShortcuts()[0];
    }
}
