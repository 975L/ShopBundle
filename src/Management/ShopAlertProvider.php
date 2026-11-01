<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Repository\ProductItemRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Says on the dashboard what two simultaneous payments can do to a limited stock: a customer paid for an article that is no longer there, and only the shop can decide what they are given instead (see ProductBasketItemProvider::countOrdered())
class ShopAlertProvider implements AlertProviderInterface
{
    // What a dashboard line can hold: past that, the shop has a bigger problem than a list would solve
    private const int MAX_NAMED = 5;

    public function __construct(
        private readonly ProductItemRepository $productItemRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // One alert while a stock stands past its cap, gone once the shop has raised the cap
    public function getAlerts(): array
    {
        $count = $this->productItemRepository->countOversold();
        if (0 === $count) {
            return [];
        }

        return [[
            'label' => $this->translator->trans('label.items_oversold', [], 'shop'),
            'description' => $this->translator->trans('description.items_oversold', ['%count%' => $count, '%items%' => $this->namesOfOversold($count)], 'shop'),
            'severity' => Config::SEVERITY_WARNING,
            'url' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(ProductCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
            'role' => (string) $this->configService->get('site-role-admin'),
        ]];
    }

    // The items to restock or refund, as the health check names them, the product list saying nothing of which they are
    private function namesOfOversold(int $count): string
    {
        $names = array_map(
            static fn (array $titles): string => implode(' / ', array_filter([$titles['product'], $titles['item']])),
            $this->productItemRepository->findOversoldTitles(self::MAX_NAMED),
        );

        return implode(', ', $names) . ($count > self::MAX_NAMED ? ', …' : '');
    }
}
