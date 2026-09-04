<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Management\ShopBlockOwnerResolver;
use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockFocusUrl;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

// The shop's index composed in the back-office: one screen editing one row, which holds the blocks the index prints above its listing. There is nothing to list, so the index action never shows a table - it opens that row, creating it the first time anyone comes here
class ShopSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopSettingsRepository $shopSettingsRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ShopSettings::class;
    }

    // Straight to the single row rather than to a table of one line, the row being created on the first visit - what makes this screen behave as a settings page and not as a catalogue
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $settings = $this->shopSettingsRepository->findSingle();

        if (null === $settings) {
            $settings = new ShopSettings();
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }

        return $this->redirect(BlockFocusUrl::build($this->adminUrlGenerator, self::class, $settings->getId()));
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return [
            // The one line the index prints above everything else, plain text and not a block: it is the shop's own sentence, and a shop that never comes here keeps the default the menu describes the shop link with
            FormField::addFieldset(t('label.shop_intro', [], 'shop')),
            TextareaField::new('intro')
                ->setLabel(t('label.shop_intro', [], 'shop'))
                ->setHelp(t('text.shop_intro_help', [], 'shop'))
                ->setNumOfRows(2)
                ->setColumns('col-12')
                ->setRequired(false),

            // Blocks: what the shop's index says above its listing, composed with UiBundle's kinds - the same collection a product sheet and a category page hold, minus the context that offers the two sheet-only kinds, which have no product to read here
            FormField::addFieldset(t('label.blocks', [], 'shop')),
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'shop'))
                ->setHelp(t('text.shop_blocks_help', [], 'shop'))
                // CollectionField's "col-md-8 col-xxl-7" default would leave a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // The sortable's own attributes, plus the one the guided project points at when it walks the user down to the blocks: the entries are numbered by the collection, so no id inside is stable enough to name (see ShopGuidedProjectProvider)
                ->setFormTypeOption('row_attr', ['data-shop-settings-blocks' => '1', ...$this->blockMoveRowAttrBuilder->build(ShopBlockOwnerResolver::TYPE_SHOP, $entity instanceof ShopSettings ? $entity->getId() : null)]),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // Opens the shop's index on the site, in a new tab - the page these blocks are composed for
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'shop'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (): string => $this->generateUrl('shop_index'))
            ->setHtmlAttributes(['target' => '_blank'])
            ->addCssClass('btn btn-secondary')
        ;

        return $actions
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->setPermission(Action::EDIT, $role)
            ->setPermission('viewOnSite', $role)
            // A single row, created by index() and never listed: nothing to add, nothing to delete, and no detail beyond what edit already shows
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.shop_index', [], 'shop'))
            ->setEntityLabelInPlural(t('label.shop_index', [], 'shop'))
            ->setPageTitle(Crud::PAGE_EDIT, t('label.shop_index', [], 'shop'))
            // The edit page and not the index one: index() redirects straight to the single row, so the screen the user actually reads is the form
            ->overrideTemplate('crud/edit', '@c975LShop/management/shop_settings_crud_edit.html.twig')
            ->setEntityPermission($this->configService->get('site-role-admin'))
        ;
    }
}
