<?php

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Basket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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
                ->setLabel('label.order_number')
                ->setFormTypeOption('disabled', 'disabled'),
            AssociationField::new('payment')
                ->setLabel('label.payment')
                ->setFormTypeOption('disabled','disabled'),
            TextField::new('status')
                ->setLabel('label.status')
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('digital')
                ->setLabel('label.digital')
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
            TextField::new('name')
                ->setLabel('label.name')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('address')
                ->setLabel('label.address')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('zip')
                ->setLabel('label.zip')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('city')
                ->setLabel('label.city')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('country')
                ->setLabel('label.country')
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
        // Paid baskets
        $filterPaid = Action::new('filterPaid', 'paid', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
                return $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'paid')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });

        // Validated baskets
        $filterValidated = Action::new('filterValidated', 'validated', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
                return $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'validated')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });

        // New baskets
        $filterNew = Action::new('filterNew', 'new', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
                return $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'new')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });


/*
// if the method is not defined in a CRUD controller, link to its route
$sendInvoice = Action::new('sendInvoice', 'Send invoice', 'fa fa-envelope')
    // if the route needs parameters, you can define them:
    // 1) using an array
    ->linkToRoute('invoice_send', [
        'send_at' => (new \DateTime('+ 10 minutes'))->format('YmdHis'),
    ])

    // 2) using a callable (useful if parameters depend on the entity instance)
    // (the type-hint of the function argument is optional but useful)
    ->linkToRoute('invoice_send', function (Basket $basket): array {
        return [
            'uuid' => $basket->getId(),
            'method' => $basket->getEmail(),
        ];
    });
*/

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $filterPaid)
            ->add(Crud::PAGE_INDEX, $filterValidated)
            ->add(Crud::PAGE_INDEX, $filterNew)
//->add(Crud::PAGE_INDEX, $sendInvoice)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::DETAIL, 'ROLE_ADMIN')
            ->setPermission('filterPaid', 'ROLE_ADMIN')
            ->setPermission('filterValidated', 'ROLE_ADMIN')
            ->setPermission('filterNew', 'ROLE_ADMIN')
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
            ->add('number')
            ->add('status')
        ;
    }
}
