<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use c975L\PaymentBundle\Controller\Management\BasketCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The articles and the orders behind each of ShopIntegrityHealthCheckProvider's counts, one link per row: a count says there is something to fix, a link is what gets it fixed
class ShopIntegrityHealthCheckAdviceProvider implements HealthCheckAdviceProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildAdvice(array $results): array
    {
        $advice = [];

        foreach ($results as $result) {
            if (ShopIntegrityHealthCheckProvider::KIND !== $result->getKind()) {
                continue;
            }

            $offenders = ($result->getDetails() ?? [])['offenders'] ?? [];
            if ([] === $offenders) {
                continue;
            }

            $advice[HealthCheckAdviceBuilder::key($result)] = [[
                'text' => $this->translator->trans('label.health_check_advice_shop_offenders', ['%count%' => \count($offenders)], 'shop'),
                'url' => null,
                'items' => array_map($this->item(...), $offenders),
            ]];
        }

        return $advice;
    }

    // The product sheet for an article, the order's own screen for a delivery that never happened - which is where each of them is looked at
    private function item(array $offender): array
    {
        $productId = $offender['productId'] ?? null;
        $basketId = $offender['basketId'] ?? null;

        return [
            'text' => $this->translator->trans('label.health_check_advice_shop_offender', [
                '%item%' => $offender['label'] ?? '',
                '%info%' => $offender['info'] ?? '',
            ], 'shop'),
            'url' => match (true) {
                null !== $productId => $this->url(ProductCrudController::class, Action::EDIT, $productId),
                null !== $basketId => $this->url(BasketCrudController::class, Action::DETAIL, $basketId),
                default => null,
            },
            'label' => null,
        ];
    }

    // A product is edited, an order is only ever read - its own CRUD disables edition, an order being an accounting record
    private function url(string $controller, string $action, int $entityId): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction($action)
            ->setEntityId($entityId)
            ->generateUrl();
    }
}
