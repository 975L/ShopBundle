<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Basket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
            ChoiceField::new('digital')
                ->setLabel('label.digital')
                ->setFormTypeOption('disabled', 'disabled')
                ->setChoices([
                    'label.digital' => 1,
                    'label.mixed' => 2,
                    'label.physical' => 3,
                ]),
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
        $filterValidated = Action::new('filterValidated', ' (03/04/2025)', 'fa fa-filter')
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

        // Send items
        $sendItems = Action::new('itemsShipped', 'label.send_items')
            ->linkToRoute('items_shipped', function (Basket $basket): array {
                return [
                    'number' => $basket->getNumber(),
                ];
            })
            ->setHtmlAttributes([
                'target' => '_blank'
            ])
            ->displayIf(function (Basket $basket): bool {
                return $basket->getStatus() === 'paid' && $basket->getNumber() !== null && $basket->getDigital() !== 1;
            });

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $filterPaid)
            ->add(Crud::PAGE_INDEX, $filterValidated)
            ->add(Crud::PAGE_INDEX, $filterNew)
            ->add(Crud::PAGE_INDEX, $sendItems)
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
