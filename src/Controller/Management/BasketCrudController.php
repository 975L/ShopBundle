<?php

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Basket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

#[IsGranted('ROLE_ADMIN')]
class BasketCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Basket::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('number')
                ->setLabel('label.number')
                ->setFormTypeOption('disabled', 'disabled'),
            AssociationField::new('payment')
                ->setLabel('label.paiement')
                ->hideOnIndex()
                ->setFormTypeOption('disabled','disabled')
                ->onlyOnDetail()
                ->formatValue(function ($value, $entity) {
                    return $entity->getPayment()->getId();
                }),
            TextField::new('status')
                ->setLabel('label.status')
                ->setFormTypeOption('disabled', 'disabled'),
            BooleanField::new('isNumeric')
                ->setLabel('label.is_numeric')
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('total')
                ->setLabel('label.total')
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('shipping')
                ->setLabel('label.shipping')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('currency')
                ->setLabel('label.currency')
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('quantity')
                ->setLabel('label.quantity')
                ->setFormTypeOption('disabled', 'disabled'),
            EmailField::new('email')
                ->setLabel('label.email')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('address')
                ->setLabel('label.address')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('city')
                ->setLabel('label.city')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('zip')
                ->setLabel('label.zip')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('country')
                ->setLabel('label.country')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('paymentIdentifier')
                ->setLabel('label.payment_identifier')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('creation')
                ->setLabel('label.creation')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel('label.modification')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DETAIL, 'ROLE_ADMIN')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }
}
