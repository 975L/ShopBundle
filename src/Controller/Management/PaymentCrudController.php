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
            TextField::new('stripe_token')
                ->setLabel('label.stripe_token')
                ->formatValue(function ($value, $payment) {
                    if (!$value) {
                        return null;
                    }

                    return sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        'https://dashboard.stripe.com/test/payments/' . $value,
                        $payment->getStripeToken()
                    );
                }),
            TextField::new('stripe_method')
                ->setLabel('label.stripe_method'),
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

        $viewStripeInvoice = Action::new('viewStripeInvoice', 'Invoice', 'fa fa-file-invoice')
            ->linkToUrl(function (Payment $payment) {
                return 'https://dashboard.stripe.com/test/payments/' . $payment->getStripeToken();
            });

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewStripeInvoice)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::DETAIL, 'ROLE_ADMIN')
            ->setPermission('viewStripeInvoice', 'ROLE_ADMIN')
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
