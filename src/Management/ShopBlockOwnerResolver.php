<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;

// Lets BlockMoveController relocate a Block of this bundle without depending on the classes themselves - the three places the shop is composed in the back-office: a product sheet, a category page and the shop's index
class ShopBlockOwnerResolver implements BlockOwnerResolverInterface
{
    // Shared with each CRUD's own blockMoveRowAttr() call, so the owner-type strings only ever exist in one place
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'product_category';
    public const TYPE_SHOP = 'shop';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly ShopSettingsRepository $shopSettingsRepository,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return \in_array($ownerType, [self::TYPE_PRODUCT, self::TYPE_CATEGORY, self::TYPE_SHOP], true);
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        return match ($ownerType) {
            self::TYPE_PRODUCT => $this->productRepository->find($ownerId),
            self::TYPE_CATEGORY => $this->productCategoryRepository->find($ownerId),
            // The shop's index holds a single row, whichever id it was created with: a block dragged onto it lands on that one rather than on an id the move screen would have to know
            self::TYPE_SHOP => $this->shopSettingsRepository->findSingle(),
            default => null,
        };
    }
}
