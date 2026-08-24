<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Controller\Management\ShopShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

// Grouped under maintenance, next to PaymentBundle's own test switch and the site's maintenance one: the three put a part of the site into a state it is not meant to serve customers in, and an admin looks for them in the same place
class ShopShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getShortcuts(): array
    {
        $enabled = (bool) $this->configService->get('shop-test-mode');

        return [
            [
                'label' => $this->translator->trans(
                    $enabled ? 'label.shop_test_mode_disable' : 'label.shop_test_mode_enable',
                    [],
                    'shop',
                ),
                'icon' => 'fas fa-vial',
                'route' => ShopShortcutController::TOGGLE_ROUTE_TEST_MODE,
                'active' => $enabled,
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
        ];
    }
}
