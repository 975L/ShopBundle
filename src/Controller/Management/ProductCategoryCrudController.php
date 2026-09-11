<?php

namespace c975L\ShopBundle\Controller\Management;

use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Management\ProductCategoryExportProvider;
use c975L\ShopBundle\Management\ProductCategoryImportProvider;
use c975L\ShopBundle\Management\ShopBlockOwnerResolver;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class ProductCategoryCrudController extends AbstractCrudController
{
    private const string TABLE = 'shop_product_category';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly ContentExporter $contentExporter,
        private readonly ContentLocaleScreen $contentLocaleScreen,
        private readonly ProductCategoryExportProvider $productCategoryExportProvider,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly ShopTranslator $shopTranslator,
        private readonly TableExporter $tableExporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductCategory::class;
    }

    public function configureFields(string $pageName): iterable
    {
        // The very same edit screen, opened on another language: what that language says of this row, and nothing else. A price, a stock, a slug and a reference are the same in every language and are written on the screen the row was written on (see ContentLocaleScreen)
        $contentLocale = Crud::PAGE_EDIT === $pageName ? $this->contentLocale() : null;
        if (null !== $contentLocale) {
            return $this->translationFields($contentLocale);
        }

        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'shop')),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'shop'))
                ->setTargetFieldName('name')
                ->hideOnIndex(),
            TextEditorField::new('description')
                ->setLabel(t('label.description', [], 'shop'))
                ->setHelp(t('text.category_description_help', [], 'shop'))
                ->setRequired(false)
                ->hideOnIndex(),
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'shop'))
                ->setRequired(false),

            // Blocks: what the category page says around its listing, composed with UiBundle's kinds - the same collection a product sheet holds, minus the context that offers the two sheet-only kinds, which have no product to read here
            FormField::addFieldset(t('label.blocks', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'shop'))
                ->hideOnIndex()
                // CollectionField's "col-md-8 col-xxl-7" default would leave a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(ShopBlockOwnerResolver::TYPE_CATEGORY, $entity instanceof ProductCategory ? $entity->getId() : null)),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // Same flat table dump as the products it classifies (see ProductCrudController)
        $exportGroup = ActionGroup::new('export', t('label.export', [], 'shop'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Same "export selection" as SiteBundle's PageCrudController::exportSelection() - the re-importable archive, where the group above dumps the raw table
        $actions->add(Crud::PAGE_INDEX, Action::new('exportSelection', t('action.export_selection', [], 'shop'), 'fa fa-file-export')
            ->createAsBatchAction()
            ->linkToCrudAction('exportSelection'));
        $actions->setPermission('exportSelection', $role);

        // Opens the public page of the category on the site, in a new tab - hidden while the category has no slug yet, which would point at a dead url
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'shop'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (ProductCategory $category): string => $this->generateUrl('category_display', ['slug' => $category->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (ProductCategory $category): bool => '' !== (string) $category->getSlug())
            ->addCssClass('btn btn-secondary')
        ;

        // Opens the first language screen straight from the list, the way SiteBundle's pages are translated - the tabs above a sheet already opened are the only other way in, and a translation screen nobody finds translates nothing
        $translateAction = $this->contentLocaleScreen
            ->action('translate', t('action.translate', [], 'shop'), 'fa fa-language', $this->shopTranslator->getTranslatableLocales())
            ->displayIf(fn (ProductCategory $category): bool => $this->shopTranslator->isActive())
            ->addCssClass('btn btn-secondary')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $translateAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, 'translate', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.translate', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            // reorder() turns priority ordering off page-wide, so "exportSelection" leads the batch bar too - "batchDelete" is left unnamed, as naming it throws where it is disabled
            ->reorder(Crud::PAGE_INDEX, ['exportSelection', Action::EDIT, 'viewOnSite', Action::DELETE])
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('exportSql', $role)
            ->setPermission('exportCsv', $role)
            ->setPermission('exportJson', $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "ProductCategory" on every screen and every button
            ->setEntityLabelInSingular(t('label.category', [], 'shop'))
            ->setEntityLabelInPlural(t('label.categories', [], 'shop'))
            ->setEntityPermission($this->configService->get('site-role-admin'))
            // Carries the language tabs above the form, and nothing at all on a site declaring a single language (see ContentLocaleScreen)
            ->overrideTemplate('crud/edit', '@c975LShop/management/product_category_crud_edit.html.twig')
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LShop/management/product_category_crud_index.html.twig')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Sql, self::TABLE, $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Csv, self::TABLE, $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Json, self::TABLE, $this->fetchExportRows());
    }

    // Exports the checked categories as a downloadable zip, meant to be re-uploaded elsewhere via ConfigBundle's ContentImportController (see ProductCategoryImportProvider) - restricted to the site's admin role, see configureActions()
    #[AdminRoute]
    public function exportSelection(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (ProductCategory::class !== $batchActionDto->getEntityFqcn()) {
            throw new BadRequestHttpException();
        }

        if (!$this->isCsrfTokenValid('ea-batch-action-exportSelection-' . $batchActionDto->getEntityFqcn(), $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $categories = $this->productCategoryRepository->findBy(['id' => $batchActionDto->getEntityIds()]);
        $data = $this->productCategoryExportProvider->serialize($categories);

        return $this->contentExporter->export(ProductCategoryImportProvider::KIND, $data['items'], $data['files']);
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `' . self::TABLE . '` ORDER BY `id`');
    }

    // The language this row is being written in, when it is not the one the site was written in (see ContentLocaleScreen)
    private function contentLocale(): ?string
    {
        return $this->contentLocaleScreen->locale($this->shopTranslator->getTranslatableLocales());
    }

    // Each translatable text, holding what that language already says or the source between brackets where it says nothing yet - unmapped, all of them, what is written here belonging to the translation table and mapped back overwriting the text the shop was written in
    /** @return list<FieldInterface> */
    private function translationFields(string $locale): array
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        // Every field keyed, empty rather than absent, on a screen reached without the entity it describes: the fields below read the answer promptValues() promises whichever way this was entered
        $values = $entity instanceof ProductCategory ? $this->shopTranslator->promptValues($entity, $locale) : array_fill_keys(ShopTranslator::CATEGORY_FIELDS, null);

        return [
            FormField::addFieldset(t('label.fieldset_this_language', ['%language%' => Locales::getName($locale, $locale)], 'shop'))
                ->setHelp(t('label.fieldset_this_language_help', [], 'shop')),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'shop'))
                ->setRequired(false)
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('data', $values['name']),
            // The very type the writing screen declares: a description is rich text there, and translating it through a plain textarea showed the markup as source and stored the answer stripped of it. Donovan goes with the textarea, the form theme only ever putting it under one
            TextEditorField::new('description')
                ->setLabel(t('label.description', [], 'shop'))
                ->setRequired(false)
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('data', $values['description']),
        ];
    }

    // What the language tabs at the top of the edit screen need, and nothing at all where the row is not saved yet or the site declares a single language
    #[\Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $id = $entity instanceof ProductCategory ? $entity->getId() : null;
        if (null !== $id && $this->shopTranslator->isActive()) {
            $this->contentLocaleScreen->addParameters($responseParameters, self::class, $id, $this->shopTranslator->getTranslatableLocales(), $this->contentLocale());
        }

        return $responseParameters;
    }

    // What a language screen wrote, handed over to be stored on the flush that saves the row and never before it (see ContentLocaleScreen::stageOnSubmit)
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $contentLocale = $this->contentLocale();

        $this->contentLocaleScreen->stageOnSubmit(
            $formBuilder,
            $contentLocale,
            ShopTranslator::CATEGORY_FIELDS,
            function (object $entity, array $values) use ($contentLocale): void {
                if ($entity instanceof ProductCategory && null !== $contentLocale) {
                    $this->shopTranslator->stage($entity, $contentLocale, $values);
                }
            }
        );

        return $formBuilder;
    }
}
