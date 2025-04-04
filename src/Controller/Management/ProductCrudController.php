<?php

namespace c975L\ShopBundle\Controller\Management;

use DateTimeImmutable;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Form\ProductItemType;
use c975L\ShopBundle\Form\ProductMediaType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

#[IsGranted('ROLE_ADMIN')]
class ProductCrudController extends AbstractCrudController
{
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
                ->setLabel('label.title'),
            SlugField::new('slug')
                ->setTargetFieldName('title')
                ->hideOnIndex(),
            IntegerField::new('position')
                ->setLabel('label.position')
                ->setRequired(false),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(ProductMediaType::class),
            FormField::addPanel('Items Management')
                ->setHelp('Add items WITHOUT media/files, then add them afterwards.')
                ->hideOnIndex(),
            CollectionField::new('items')
                ->hideOnIndex()
                ->setEntryType(ProductItemType::class),
            TextEditorField::new('description')
                ->setLabel('label.description')
                ->hideOnIndex(),
            DateTimeField::new('creation')
                ->setLabel('label.creation')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel('label.modification')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
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
