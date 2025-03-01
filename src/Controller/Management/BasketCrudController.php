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
                ->setLabel('label.number'),
            AssociationField::new('payment')
                ->setLabel('label.paiement')
                ->hideOnIndex()
                ->setFormTypeOption('disabled','disabled')
                ->onlyOnDetail()
                ->formatValue(function ($value, $entity) {
                    return $entity->getPayment()->getNumber();
                }),
            TextField::new('identifier')
                ->setLabel('label.identifier')
                ->hideOnIndex(),
            TextField::new('status')
                ->setLabel('label.status'),
            BooleanField::new('isNumeric')
                ->setLabel('label.is_numeric'),
            IntegerField::new('total')
                ->setLabel('label.total'),
            IntegerField::new('shipping')
                ->setLabel('label.shipping'),
            TextField::new('currency')
                ->setLabel('label.currency'),
            IntegerField::new('quantity')
                ->setLabel('label.quantity'),
            EmailField::new('email')
                ->setLabel('label.email'),
            TextField::new('address')
                ->setLabel('label.address')
                ->hideOnIndex(),
            TextField::new('city')
                ->setLabel('label.city')
                ->hideOnIndex(),
            TextField::new('zip')
                ->setLabel('label.zip')
                ->hideOnIndex(),
            TextField::new('country')
                ->setLabel('label.country')
                ->hideOnIndex(),
            TextField::new('paymentIdentifier')
                ->setLabel('label.payment_identifier')
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
            ->setEntityPermission('ROLE_EDITOR')
        ;
    }
}
