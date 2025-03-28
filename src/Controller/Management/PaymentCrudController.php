<?php

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Payment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

#[IsGranted('ROLE_ADMIN')]
class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('basket')
                ->setLabel('label.basket')
                ->setFormTypeOption('disabled','disabled'),
            BooleanField::new('isFinished')
                ->setLabel('label.is_finished'),
            IntegerField::new('amount')
                ->setLabel('label.amount'),
            TextField::new('currency')
                ->setLabel('label.currency'),
            IntegerField::new('stripe_fee')
                ->setLabel('label.stripe_fee'),
            TextField::new('stripe_token')
                ->setLabel('label.stripe_token'),
            TextField::new('stripe_token_type')
                ->setLabel('label.stripe_token_type'),
            EmailField::new('stripe_email')
                ->setLabel('label.stripe_email'),
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
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isFinished'))
        ;
    }
}
