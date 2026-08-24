<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Controller\Management\ShopSettingsCrudController;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of whatever owns a given Block - a product sheet, a category page or the shop's index, which then all behave as a page's blocks do in SiteBundle. Discovered on its interface alone, no tag needed (see UiBundle's BlockEditUrlProviderPass)
class ShopBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly ShopSettingsRepository $shopSettingsRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getEditUrls(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $urls = [];
        foreach ($this->productRepository->findByBlockIds($blockIds) as $product) {
            $urls += $this->ownerUrls($product, $product->getId(), ProductCrudController::class, $blockIds);
        }

        foreach ($this->productCategoryRepository->findByBlockIds($blockIds) as $category) {
            $urls += $this->ownerUrls($category, $category->getId(), ProductCategoryCrudController::class, $blockIds);
        }

        foreach ($this->shopSettingsRepository->findByBlockIds($blockIds) as $settings) {
            $urls += $this->ownerUrls($settings, $settings->getId(), ShopSettingsCrudController::class, $blockIds);
        }

        return $urls;
    }

    // The owner's edit screen, opened on the very row the hovered block was composed in - a sheet holds dozens of them
    /**
     * @param int[] $blockIds
     *
     * @return array<int, string>
     */
    private function ownerUrls(HasBlocksInterface $owner, ?int $ownerId, string $controller, array $blockIds): array
    {
        $urls = [];
        foreach ($owner->getBlocks() as $block) {
            if (\in_array($block->getId(), $blockIds, true)) {
                $urls[$block->getId()] = BlockFocusUrl::build($this->adminUrlGenerator, $controller, $ownerId, $block);
            }
        }

        return $urls;
    }
}
