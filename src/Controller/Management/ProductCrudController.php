<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Form\ProductItemType;
use c975L\ShopBundle\Form\ProductMediaType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel(new TranslatableMessage('label.title', [], 'shop')),
            SlugField::new('slug')
                ->setTargetFieldName('title')
                ->hideOnIndex(),
            AssociationField::new('categories')
                ->setLabel(new TranslatableMessage('label.categories', [], 'shop'))
                ->setRequired(false)
                ->setQueryBuilder(
                    fn ($queryBuilder) => $queryBuilder->orderBy('entity.name', 'ASC')
                ),
            IntegerField::new('position')
                ->setLabel(new TranslatableMessage('label.position', [], 'shop'))
                ->setRequired(false),
            TextEditorField::new('description')
                ->setLabel(new TranslatableMessage('label.description', [], 'shop'))
                ->hideOnIndex(),
            DateField::new('availableAt')
                ->setLabel(new TranslatableMessage('label.available_at', [], 'shop')),

            // Media management
            FormField::addFieldset(new TranslatableMessage('label.media', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(ProductMediaType::class),

            // Items
            FormField::addFieldset(new TranslatableMessage('label.items', [], 'shop'))
                ->setHelp(new TranslatableMessage('text.items_management', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('items')
                ->hideOnIndex()
                ->setEntryType(ProductItemType::class),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(new TranslatableMessage('label.creation', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel(new TranslatableMessage('label.modification', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, $this->configService->get('site-role-needed'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setDefaultSort(['position' => 'ASC'])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
        ;
    }
}
