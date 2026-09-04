<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Form\ProductItemType;
use c975L\ShopBundle\Form\ProductMediaType;
use c975L\ShopBundle\Management\ProductDuplicator;
use c975L\ShopBundle\Management\ProductExportProvider;
use c975L\ShopBundle\Management\ProductImportProvider;
use c975L\ShopBundle\Management\ShopBlockOwnerResolver;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Repository\FavoriteRepository;
use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class ProductCrudController extends AbstractCrudController
{
    private const string TABLE = 'shop_product';

    // The row actions are links like every other one of the index, so their token rides in the url rather than in a form - a GET an <img> on a third-party page would otherwise fire on a logged-in admin (same pattern as SiteBundle's own trash actions)
    public const string DUPLICATE_CSRF_TOKEN = 'shop_product_duplicate';
    public const string RESTORE_CSRF_TOKEN = 'shop_product_restore';
    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'shop_product_delete_permanently';

    // Where a product's public sheet is served, the prefix a redirect and a "gone" row are written against
    private const string PRODUCT_PATH = '/shop/products/';

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly ContentExporter $contentExporter,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ProductExportProvider $productExportProvider,
        private readonly RedirectRepository $redirectRepository,
        private readonly RequestStack $requestStack,
        private readonly TableExporter $tableExporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        // Trashed products are hidden by definition (see deleteEntity() and Product::setIsDeleted()), so the column would hold "yes" for every row of that view - taken off rather than left there saying nothing
        $hiddenField = BooleanField::new('hidden')
            ->setLabel(t('label.hidden', [], 'shop'))
            ->setHelp(t('text.hidden', [], 'shop'));
        if ($this->isTrash()) {
            $hiddenField->hideOnIndex();
        }

        return [
            IdField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'shop')),
            $hiddenField,
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'shop'))
                ->setTargetFieldName('title')
                ->hideOnIndex(),
            AssociationField::new('categories')
                ->setLabel(t('label.categories', [], 'shop'))
                ->setRequired(false)
                ->setQueryBuilder(
                    fn ($queryBuilder) => $queryBuilder->orderBy('entity.name', 'ASC')
                ),
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'shop'))
                ->setRequired(false),
            TextEditorField::new('description')
                ->setLabel(t('label.description', [], 'shop'))
                ->hideOnIndex(),
            TextField::new('brand')
                ->setLabel(t('label.brand', [], 'shop'))
                ->setHelp(t('label.brand_help', [], 'shop'))
                ->setRequired(false)
                ->hideOnIndex(),
            TextField::new('age')
                ->setLabel(t('label.age', [], 'shop'))
                ->setHelp(t('label.age_help', [], 'shop'))
                ->setRequired(false)
                ->hideOnIndex(),
            DateField::new('availableAt')
                ->setLabel(t('label.available_at', [], 'shop')),

            // What goes with this product, chosen by hand: the calculated affinities say nothing until something has been sold, which is exactly when a new catalogue needs cross-selling most
            AssociationField::new('relatedProducts')
                ->setLabel(t('label.related_products', [], 'shop'))
                ->setHelp(t('label.related_products_help', [], 'shop'))
                ->setRequired(false)
                ->hideOnIndex()
                ->setFormTypeOption('by_reference', false)
                ->setQueryBuilder(
                    fn ($queryBuilder) => $queryBuilder
                        ->andWhere('entity.isDeleted = false')
                        ->orderBy('entity.title', 'ASC')
                ),

            // The card this product sells, if it sells one: what is printed on it beside the amount its items carry (see ProductItem::$giftCardValue). Left alone on an ordinary product, whose sheet the fieldset simply says nothing about
            FormField::addFieldset(t('label.gift_card_design', [], 'shop'))
                ->setHelp(t('label.gift_card_design_help', [], 'shop'))
                ->hideOnIndex(),
            TextField::new('giftCardText')
                ->setLabel(t('label.gift_card_text', [], 'shop'))
                ->setHelp(t('label.gift_card_text_help', [], 'shop'))
                ->setRequired(false)
                ->hideOnIndex(),
            BooleanField::new('giftCardScratch')
                ->setLabel(t('label.gift_card_scratch', [], 'shop'))
                ->setHelp(t('label.gift_card_scratch_help', [], 'shop'))
                ->hideOnIndex(),

            // Media management
            FormField::addFieldset(t('label.media', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->setLabel(t('label.media', [], 'shop'))
                ->hideOnIndex()
                ->setEntryType(ProductMediaType::class),

            // Items
            FormField::addFieldset(t('label.items', [], 'shop'))
                ->setHelp(t('text.items_management', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('items')
                ->setLabel(t('label.items', [], 'shop'))
                ->hideOnIndex()
                ->setEntryType(ProductItemType::class)
                // What the guided projects point at when they walk the user down to the prices: the entries themselves are numbered by the collection, so no id inside is stable enough to name (see ShopGuidedProjectProvider)
                ->setFormTypeOption('row_attr', ['data-shop-product-items' => '1']),

            // Blocks: what the product sheet says around its items, composed with UiBundle's kinds
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
                // The context the two sheet-only kinds declare (see config/services.yaml): it adds them to the picker here, and keeps them out of every other blocks collection of the site, where they would have no product to read
                ->setFormTypeOption('entry_options.context', 'shop_product')
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(ShopBlockOwnerResolver::TYPE_PRODUCT, $entity instanceof Product ? $entity->getId() : null)),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // The catalogue leaves the site as a flat table dump (see ConfigBundle's TableExporter), the same way the baskets and the payments do: a product's categories and blocks live in their own link tables, which c975l:config:export-tables carries when a whole catalogue has to be replayed elsewhere
        $exportGroup = ActionGroup::new('export', t('label.export', [], 'shop'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Same "export selection" as SiteBundle's PageCrudController::exportSelection() - the archive that carries a product whole (its pictures, its items and their files, its blocks and its categories) and can be uploaded back elsewhere, where the group above dumps the raw table
        $actions->add(Crud::PAGE_INDEX, Action::new('exportSelection', t('action.export_selection', [], 'shop'), 'fa fa-file-export')
            ->createAsBatchAction()
            ->linkToCrudAction('exportSelection'));
        $actions->setPermission('exportSelection', $role);

        // Opens the public page of the product on the site, in a new tab - hidden while the product has no slug yet, which would point at a dead url
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'shop'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (Product $product): string => $this->generateUrl('product_display', ['slug' => $product->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Product $product): bool => !$product->isHidden() && !$product->isDeleted() && '' !== (string) $product->getSlug())
            ->addCssClass('btn btn-secondary')
        ;

        // Copies the product with its pictures, its items and its blocks, then opens the copy - what a catalogue of near-identical sheets is written with, rather than by retyping each one
        $duplicateAction = Action::new('duplicate', t('action.duplicate', [], 'shop'), 'fa fa-copy')
            ->linkToUrl(fn (Product $product): string => $this->tokenizedUrl('duplicate', $product, self::DUPLICATE_CSRF_TOKEN))
            ->displayIf(static fn (Product $product): bool => !$product->isDeleted())
            ->askConfirmation(t('confirm.duplicate', [], 'shop'))
            ->addCssClass('btn btn-secondary')
        ;

        // Opens the sheet of a product that is not online yet, in a new tab - the same page the visitor will read, minus the block cache and with a banner saying so
        $previewAction = Action::new('preview', t('action.preview', [], 'shop'), 'fa fa-eye')
            ->linkToUrl(fn (Product $product): string => $this->generateUrl('product_preview', ['slug' => $product->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Product $product): bool => $product->isHidden() && !$product->isDeleted() && '' !== (string) $product->getSlug())
            ->addCssClass('btn btn-secondary')
        ;

        // Takes a product back out of the recycle bin, with everything it still holds - it comes back hidden, its own switch having been turned on when it was trashed
        $restoreAction = Action::new('restore', t('action.restore', [], 'shop'), 'fa fa-trash-restore')
            ->linkToUrl(fn (Product $product): string => $this->tokenizedUrl('restore', $product, self::RESTORE_CSRF_TOKEN, true))
            ->displayIf(static fn (Product $product): bool => $product->isDeleted())
            ->addCssClass('btn btn-secondary')
        ;

        // Removes the product for good, its pictures and its files with it - only reachable from the recycle bin
        $deletePermanentlyAction = Action::new('deletePermanently', t('action.delete_permanently', [], 'shop'), 'fa fa-trash')
            ->linkToUrl(fn (Product $product): string => $this->tokenizedUrl('deletePermanently', $product, self::DELETE_PERMANENTLY_CSRF_TOKEN, true))
            ->displayIf(static fn (Product $product): bool => $product->isDeleted())
            ->askConfirmation(t('confirm.delete_permanently', [], 'shop'))
            ->asDangerAction()
            ->addCssClass('btn btn-danger')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_INDEX, $this->trashAction())
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $deletePermanentlyAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $previewAction)
            ->add(Crud::PAGE_EDIT, $duplicateAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Product $product): bool => !$product->isDeleted()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, 'preview', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.preview', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, 'duplicate', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.duplicate', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, 'restore', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.restore', [], 'shop'),
            ))
            ->update(Crud::PAGE_INDEX, 'deletePermanently', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete_permanently', [], 'shop'),
            ))
            // "Delete" only moves the product to the recycle bin here, and says so - what actually removes it is the action of that view
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action
                    ->setIcon('fa fa-box-archive')
                    ->askConfirmation(t('confirm.move_to_trash', [], 'shop'))
                    ->displayIf(static fn (Product $product): bool => !$product->isDeleted()),
                $this->translator->trans('action.move_to_trash', [], 'shop'),
            ))
            // reorder() turns priority ordering off page-wide, so "exportSelection" leads the batch bar too - "batchDelete" is left unnamed, as naming it throws where it is disabled
            ->reorder(Crud::PAGE_INDEX, ['exportSelection', Action::EDIT, 'viewOnSite', 'preview', 'duplicate', 'restore', Action::DELETE, 'deletePermanently'])
            ->reorder(Crud::PAGE_EDIT, ['viewOnSite', 'preview', 'duplicate'])
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('preview', $role)
            ->setPermission('duplicate', $role)
            ->setPermission('trash', $role)
            ->setPermission('restore', $role)
            ->setPermission('deletePermanently', $role)
            ->setPermission('reorder', $role)
            ->setPermission('exportSql', $role)
            ->setPermission('exportCsv', $role)
            ->setPermission('exportJson', $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // Whether the index is showing the recycle bin rather than the catalogue - the one flag the whole screen reads, from the fields to the query
    private function isTrash(): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');
    }

    // Toggles between "recycle bin" and "back to the products", depending on which of the two is being shown
    private function trashAction(): Action
    {
        $action = $this->isTrash()
            ? Action::new('trash', t('label.products', [], 'shop'), 'fa fa-box-open')
                ->linkToUrl(fn (): string => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl())
            : Action::new('trash', t('action.trash', [], 'shop'), 'fa fa-trash-alt')
                ->linkToUrl(fn (): string => $this->trashIndexUrl());

        return $action
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary')
        ;
    }

    // The url of a row action, its csrf token in the query string - the trash ones come back to the recycle bin, so they carry the flag that view is read from
    private function tokenizedUrl(string $action, Product $product, string $tokenId, bool $trash = false): string
    {
        $urlGenerator = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($product->getId())
            ->set('token', $this->csrfTokenManager->getToken($tokenId)->getValue());

        if ($trash) {
            $urlGenerator->set('trash', 1);
        }

        return $urlGenerator->generateUrl();
    }

    // The recycle bin both trash actions come back to, whether they ran or were refused
    private function trashIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('trash', 1)
            ->generateUrl();
    }

    // The catalogue, or the recycle bin - never the two mixed
    #[\Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isDeleted = :isDeleted')
            ->setParameter('isDeleted', $this->isTrash())
        ;
    }

    // Deleting a product only moves it to the recycle bin, with everything it holds: its url answers 410 from there, and it is restored or removed for good from that view alone
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            parent::deleteEntity($entityManager, $entityInstance);

            return;
        }

        $entityInstance->setIsDeleted(true);
        $entityManager->flush();
    }

    // A renamed product leaves its old url behind, which visitors have bookmarked and search engines have indexed - a 301 sends both to the new one rather than to a 404
    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $originalSlug = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance)['slug'] ?? null;
            if (null !== $originalSlug && $originalSlug !== $entityInstance->getSlug()) {
                $this->redirectSlugChange($entityManager, (string) $originalSlug, (string) $entityInstance->getSlug());
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    // Redirects the old url to the new one, reusing the row the old slug already had
    private function redirectSlugChange(EntityManagerInterface $entityManager, string $oldSlug, string $newSlug): void
    {
        $fromPath = self::PRODUCT_PATH . $oldSlug;
        $toUrl = self::PRODUCT_PATH . $newSlug;

        // Removes any redirect starting from the new slug, otherwise renaming a product back to a name it already had builds a loop
        $reverseRedirect = $this->redirectRepository->findOneByFromPath($toUrl);
        if (null !== $reverseRedirect) {
            $entityManager->remove($reverseRedirect);
        }

        $redirect = $this->redirectRepository->findOneByFromPath($fromPath)
            ?? new Redirect()->setFromPath($fromPath);

        $entityManager->persist($redirect->setToUrl($toUrl)->setPermanent(true));
    }

    // Takes a product back out of the recycle bin, untouched - it comes back hidden, to be read once before it is shown again
    #[AdminRoute(options: ['methods' => ['GET']])]
    public function restore(Request $request, ProductRepository $productRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::RESTORE_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->trashIndexUrl());
        }

        $product = $productRepository->find($request->query->getInt('entityId'));
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        $product->setIsDeleted(false);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.product_restored', [], 'shop'));

        return $this->redirect($this->trashIndexUrl());
    }

    // Removes the product for good, its pictures and its downloadable files with it - only reachable from the recycle bin
    #[AdminRoute(options: ['methods' => ['GET']])]
    public function deletePermanently(Request $request, ProductRepository $productRepository, EntityManagerInterface $entityManager, RatingRepository $ratingRepository, FavoriteRepository $favoriteRepository, ReviewRepository $reviewRepository): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::DELETE_PERMANENTLY_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->trashIndexUrl());
        }

        $product = $productRepository->find($request->query->getInt('entityId'));
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        $this->writeGoneRedirect($entityManager, $product);

        // The customers' votes, which hang off "shop_product" + id rather than off a relation (see c975L\UiBundle\Entity\Rating) and so are cascaded by nothing. Dropped here and not when the product is trashed: one that can still be restored must find its ratings where it left them
        $ratingRepository->deleteForOwner('shop_product', (int) $product->getId());

        // The wishlists it was put aside on, held the same way and cascaded by nothing either (see c975L\UiBundle\Entity\Favorite). Dropped here and not on the trash for the same reason: a restored product must find itself on the lists it was on
        $favoriteRepository->deleteForOwner('shop_product', (int) $product->getId());

        // What the customers wrote about it, held the same way again (see c975L\UiBundle\Entity\Review) - a sheet nobody can reach any more must not keep its reviews reachable
        $reviewRepository->deleteForOwner('shop_product', (int) $product->getId());

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.product_deleted_permanently', [], 'shop'));

        return $this->redirect($this->trashIndexUrl());
    }

    // The 410 the recycle bin served only lasts as long as the product can be restored, and the url would fall back to a plain 404 once the row is gone - a "gone" Redirect keeps answering 410 for good, which a search engine acts on far faster. Redirects pointing at that url are turned into "gone" rows too rather than left dangling, and a path an admin already covered deliberately is left alone, a target saying more than a dead end
    private function writeGoneRedirect(EntityManagerInterface $entityManager, Product $product): void
    {
        $fromPath = self::PRODUCT_PATH . $product->getSlug();

        foreach ($this->redirectRepository->findByToUrl($fromPath) as $redirect) {
            $redirect->setGone(true)->setToUrl(null);
        }

        if (null === $this->redirectRepository->findOneByFromPath($fromPath)) {
            $entityManager->persist(new Redirect()->setFromPath($fromPath)->setGone(true));
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Product" on every screen and every button
            ->setEntityLabelInSingular(t('label.product', [], 'shop'))
            ->setEntityLabelInPlural(t('label.products', [], 'shop'))
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LShop/management/product_crud_index.html.twig')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
        ;
    }

    // Persists a new drag-and-drop order for the products (see product_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js)
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager, ProductRepository $productRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('shop_product_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_map(intval(...), (array) ($payload['ids'] ?? []));

        // Read-only, so the catalogue never enters the unit of work: a flush() would run ProductListener on every managed product, not only on the reordered ones, and restamp the whole shop's modification date and author
        $products = $productRepository->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->setHint(Query::HINT_READ_ONLY, true)
            ->getResult()
        ;

        $before = [];
        foreach ($products as $product) {
            $before[$product->getId()] = $product->getPosition();
        }

        $positions = $this->applyOrder($products, $ids);

        // The new slots are written one by one rather than flushed, applyOrder() renumbering the whole catalogue while only the rows that actually moved need an update
        $update = $entityManager->createQuery('UPDATE ' . Product::class . ' p SET p.position = :position WHERE p.id = :id');
        foreach ($products as $product) {
            if ($before[$product->getId()] === $product->getPosition()) {
                continue;
            }
            $update->setParameter('position', $product->getPosition())
                ->setParameter('id', $product->getId())
                ->execute()
            ;
        }

        // What was persisted, so the index shows the new numbers without a reload
        return new JsonResponse(['positions' => $positions]);
    }

    // The dropped rows take back the slots they held among the whole catalogue, which is then renumbered from 0 - the index is paginated and unfiltered, unlike SiteBundle's collection items, so numbering the dropped page from 0 would collide with every other page. Returns the new position of each product the payload named, the only ones the browser has a row for.
    private function applyOrder(array $products, array $ids): array
    {
        $dropped = array_values(array_filter($products, fn (Product $product) => in_array($product->getId(), $ids, true)));
        if (count($dropped) !== count($ids)) {
            throw $this->createAccessDeniedException();
        }

        $byId = [];
        foreach ($dropped as $product) {
            $byId[$product->getId()] = $product;
        }

        // Same slots, filled in the submitted order
        $slots = array_keys(array_filter($products, fn (Product $product) => isset($byId[$product->getId()])));
        foreach (array_values($ids) as $index => $id) {
            $products[$slots[$index]] = $byId[$id];
        }

        $positions = [];
        foreach (array_values($products) as $position => $product) {
            $product->setPosition($position);
            if (isset($byId[$product->getId()])) {
                $positions[$product->getId()] = $position;
            }
        }

        return $positions;
    }

    // Copies the product the action was clicked on and opens the copy, which is the screen it has to be named and priced on before it is left in the catalogue: the copy is hidden, so nothing of it is on sale before that screen is filled in
    #[AdminRoute(options: ['methods' => ['GET']])]
    public function duplicate(Request $request, ProductRepository $productRepository, ProductDuplicator $productDuplicator): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::DUPLICATE_CSRF_TOKEN, $request->query->getString('token'))) {
            throw $this->createAccessDeniedException();
        }

        $product = $productRepository->find($request->query->getInt('entityId'));
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        $copy = $productDuplicator->duplicate($product);
        $this->addFlash('success', $this->translator->trans('flash.product_duplicated', [], 'shop'));

        return $this->redirect($this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($copy->getId())
            ->generateUrl());
    }

    // Exports the checked products (with their pictures, their items and the files those are bought for, their blocks and their categories) as a downloadable zip, meant to be re-uploaded elsewhere via ConfigBundle's ContentImportController (see ProductImportProvider) - restricted to the site's admin role, see configureActions()
    #[AdminRoute]
    public function exportSelection(AdminContext $context, BatchActionDto $batchActionDto, ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (Product::class !== $batchActionDto->getEntityFqcn()) {
            throw new BadRequestHttpException();
        }

        if (!$this->isCsrfTokenValid('ea-batch-action-exportSelection-' . $batchActionDto->getEntityFqcn(), $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $products = $productRepository->findBy(['id' => $batchActionDto->getEntityIds()]);
        $data = $this->productExportProvider->serialize($products);

        return $this->contentExporter->export(ProductImportProvider::KIND, $data['items'], $data['files']);
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

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `' . self::TABLE . '` ORDER BY `id`');
    }
}
