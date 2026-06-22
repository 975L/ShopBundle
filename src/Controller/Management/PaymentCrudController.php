<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Payment;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class PaymentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('basket')
                ->setLabel(new TranslatableMessage('label.basket', [], 'shop'))
                ->setFormTypeOption('disabled','disabled'),
            BooleanField::new('isFinished')
                ->setLabel(new TranslatableMessage('label.is_finished', [], 'shop')),
            IntegerField::new('amount')
                ->setLabel(new TranslatableMessage('label.amount', [], 'shop')),
            TextField::new('currency')
                ->setLabel(new TranslatableMessage('label.currency', [], 'shop')),
            TextField::new('stripe_token')
                ->setLabel(new TranslatableMessage('label.stripe_token', [], 'shop'))
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
                ->setLabel(new TranslatableMessage('label.stripe_method', [], 'shop')),
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

        $viewStripeInvoice = Action::new('viewStripeInvoice', 'Invoice', 'fa fa-file-invoice')
            ->linkToUrl(function (Payment $payment) {
                return 'https://dashboard.stripe.com/test/payments/' . $payment->getStripeToken();
            });

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewStripeInvoice)
            ->setPermission(Action::DELETE, $this->configService->get('site-role-needed'))
            ->setPermission(Action::DETAIL, $this->configService->get('site-role-needed'))
            ->setPermission('viewStripeInvoice', $this->configService->get('site-role-needed'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isFinished'))
        ;
    }
}
