<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Controller\Management\ProductCategoryCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided projects, running the 8000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They follow the order a catalog is actually filled: the category classes the products, so it comes first, then the product and the item carrying its price, then the two things an item can be beyond a parcel, and last the two occasional tasks. Creating a category and creating a product are two projects rather than the one task they feel like: only the opening step of a project carries an url, everything after it walking the screen the user has been sent to, highlighting the button or the field they are meant to use next - one they click themselves, which brings the panel back on that very step (see ConfigBundle's assets/js/guided-project.js)
class ShopGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        // The test mode is flipped from the dashboard's own shortcut, not from a screen of this bundle, so its url comes from the router rather than from EasyAdmin's generator
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->categoryProject(),
            $this->productProject(),
            $this->downloadableProject(),
            $this->giftCardProject(),
            $this->testModeProject(),
            $this->exportProject(),
        ];
    }

    // What classes the catalog, and the one thing a shop needs before it holds anything to sell
    private function categoryProject(): array
    {
        return [
            'slug' => 'shop-category',
            'label' => 'label.guided_project_shop_category',
            'description' => 'description.guided_project_shop_category',
            'translation_domain' => 'shop',
            'order' => 8010,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_category_open',
                    'description' => 'description.guided_step_shop_category_open',
                    'url' => $this->indexUrl(ProductCategoryCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_shop_category_new',
                    'description' => 'description.guided_step_shop_category_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_shop_category_name',
                    'description' => 'description.guided_step_shop_category_name',
                    'highlight' => '#ProductCategory_name',
                ],
                [
                    'label' => 'label.guided_step_shop_category_slug',
                    'description' => 'description.guided_step_shop_category_slug',
                    'highlight' => '#ProductCategory_slug',
                ],
                [
                    'label' => 'label.guided_step_shop_category_description',
                    'description' => 'description.guided_step_shop_category_description',
                    // TextEditorField renders a trix editor over the input holding the value, which is the one carrying the field's own id
                    'highlight' => 'trix-editor[input="ProductCategory_description"]',
                ],
                [
                    'label' => 'label.guided_step_shop_category_position',
                    'description' => 'description.guided_step_shop_category_position',
                    'highlight' => '#ProductCategory_position',
                ],
                [
                    'label' => 'label.guided_step_shop_category_save',
                    'description' => 'description.guided_step_shop_category_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_shop_category_empty',
                    'description' => 'description.guided_step_shop_category_empty',
                ],
            ],
        ];
    }

    // The product holds what the visitor reads, its items hold what they pay - one sheet without the other sells nothing
    private function productProject(): array
    {
        return [
            'slug' => 'shop-product',
            'label' => 'label.guided_project_shop_product',
            'description' => 'description.guided_project_shop_product',
            'translation_domain' => 'shop',
            'order' => 8020,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_product_open',
                    'description' => 'description.guided_step_shop_product_open',
                    'url' => $this->indexUrl(ProductCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_shop_product_new',
                    'description' => 'description.guided_step_shop_product_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_shop_product_title',
                    'description' => 'description.guided_step_shop_product_title',
                    'highlight' => '#Product_title',
                ],
                [
                    'label' => 'label.guided_step_shop_product_categories',
                    'description' => 'description.guided_step_shop_product_categories',
                    // AssociationField is rendered by tom-select, which hides the original select behind the wrapper it inserts right after it
                    'highlight' => '#Product_categories + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_shop_product_available_at',
                    'description' => 'description.guided_step_shop_product_available_at',
                    'highlight' => '#Product_availableAt',
                ],
                [
                    'label' => 'label.guided_step_shop_product_items',
                    'description' => 'description.guided_step_shop_product_items',
                    'highlight' => '[data-shop-product-items]',
                ],
                [
                    'label' => 'label.guided_step_shop_product_item_add',
                    'description' => 'description.guided_step_shop_product_item_add',
                    'highlight' => '[data-shop-product-items] .field-collection-add-button',
                ],
                [
                    'label' => 'label.guided_step_shop_product_price',
                    'description' => 'description.guided_step_shop_product_price',
                ],
                [
                    'label' => 'label.guided_step_shop_product_published',
                    'description' => 'description.guided_step_shop_product_published',
                    'highlight' => '#Product_isPublished',
                ],
                [
                    'label' => 'label.guided_step_shop_product_save',
                    'description' => 'description.guided_step_shop_product_save',
                    'highlight' => '.action-saveAndReturn',
                ],
            ],
        ];
    }

    // Selling a file rather than a parcel, the one sale where nothing is ever shipped and the delivery is an email
    private function downloadableProject(): array
    {
        return [
            'slug' => 'shop-downloadable',
            'label' => 'label.guided_project_shop_downloadable',
            'description' => 'description.guided_project_shop_downloadable',
            'translation_domain' => 'shop',
            'order' => 8030,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_downloadable_open',
                    'description' => 'description.guided_step_shop_downloadable_open',
                    'url' => $this->indexUrl(ProductCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_shop_downloadable_edit',
                    'description' => 'description.guided_step_shop_downloadable_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_shop_downloadable_items',
                    'description' => 'description.guided_step_shop_downloadable_items',
                    'highlight' => '[data-shop-product-items]',
                ],
                [
                    'label' => 'label.guided_step_shop_downloadable_file',
                    'description' => 'description.guided_step_shop_downloadable_file',
                ],
                [
                    'label' => 'label.guided_step_shop_downloadable_save',
                    'description' => 'description.guided_step_shop_downloadable_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_shop_downloadable_delivery',
                    'description' => 'description.guided_step_shop_downloadable_delivery',
                ],
            ],
        ];
    }

    // A card is a product like another, except that what it is worth is typed on its item and what it looks like is decided here - PaymentBundle mints the code once the sale goes through
    private function giftCardProject(): array
    {
        return [
            'slug' => 'shop-gift-card',
            'label' => 'label.guided_project_shop_gift_card',
            'description' => 'description.guided_project_shop_gift_card',
            'translation_domain' => 'shop',
            'order' => 8040,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_gift_card_open',
                    'description' => 'description.guided_step_shop_gift_card_open',
                    'url' => $this->indexUrl(ProductCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_edit',
                    'description' => 'description.guided_step_shop_gift_card_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_text',
                    'description' => 'description.guided_step_shop_gift_card_text',
                    'highlight' => '#Product_giftCardText',
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_scratch',
                    'description' => 'description.guided_step_shop_gift_card_scratch',
                    'highlight' => '#Product_giftCardScratch',
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_value',
                    'description' => 'description.guided_step_shop_gift_card_value',
                    'highlight' => '[data-shop-product-items]',
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_save',
                    'description' => 'description.guided_step_shop_gift_card_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_shop_gift_card_issued',
                    'description' => 'description.guided_step_shop_gift_card_issued',
                ],
            ],
        ];
    }

    // A catalog being set up says so to whoever lands on it, which is what stands between a rehearsal and a real order
    private function testModeProject(): array
    {
        return [
            'slug' => 'shop-test-mode',
            'label' => 'label.guided_project_shop_test_mode',
            'description' => 'description.guided_project_shop_test_mode',
            'translation_domain' => 'shop',
            'order' => 8050,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_test_mode_open',
                    'description' => 'description.guided_step_shop_test_mode_open',
                    'url' => $this->urlGenerator->generate('management'),
                ],
                [
                    'label' => 'label.guided_step_shop_test_mode_enable',
                    'description' => 'description.guided_step_shop_test_mode_enable',
                    'highlight' => 'form[action$="/shop/test-mode-toggle"] button',
                ],
                [
                    'label' => 'label.guided_step_shop_test_mode_banner',
                    'description' => 'description.guided_step_shop_test_mode_banner',
                ],
                [
                    'label' => 'label.guided_step_shop_test_mode_disable',
                    'description' => 'description.guided_step_shop_test_mode_disable',
                    'highlight' => 'form[action$="/shop/test-mode-toggle"] button',
                ],
            ],
        ];
    }

    // Moving a catalog to another site, the one task where the export is only half of it - the archive is re-read by ConfigBundle's own import screen
    private function exportProject(): array
    {
        return [
            'slug' => 'shop-export',
            'label' => 'label.guided_project_shop_export',
            'description' => 'description.guided_project_shop_export',
            'translation_domain' => 'shop',
            'order' => 8060,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_shop_export_open',
                    'description' => 'description.guided_step_shop_export_open',
                    'url' => $this->indexUrl(ProductCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_shop_export_select',
                    'description' => 'description.guided_step_shop_export_select',
                    'highlight' => '#form-batch-checkbox-all',
                ],
                [
                    'label' => 'label.guided_step_shop_export_run',
                    'description' => 'description.guided_step_shop_export_run',
                    'highlight' => '.action-exportSelection',
                ],
                [
                    'label' => 'label.guided_step_shop_export_import',
                    'description' => 'description.guided_step_shop_export_import',
                ],
            ],
        ];
    }

    // The bar every screen these projects walk sets on its own index (see ProductCrudController and ProductCategoryCrudController), so a parcours is never offered to someone its very first step turns away
    private function roleNeeded(): string
    {
        return $this->configService->get('site-role-admin');
    }

    private function indexUrl(string $controller): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
