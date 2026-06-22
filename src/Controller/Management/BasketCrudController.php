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
use c975L\ShopBundle\Entity\Basket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class BasketCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Basket::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id')
                ->setFormTypeOption('disabled', 'disabled')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('number')
                ->setLabel(new TranslatableMessage('label.order_number', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            AssociationField::new('payment')
                ->setLabel(new TranslatableMessage('label.payment', [], 'shop'))
                ->setFormTypeOption('disabled','disabled'),
            TextField::new('status')
                ->setLabel(new TranslatableMessage('label.status', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            ChoiceField::new('contentFlags')
                ->setLabel(new TranslatableMessage('label.content', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled')
                ->setChoices([
                    'label.digital' => Basket::CONTENT_FLAG_DIGITAL,
                    'label.physical' => Basket::CONTENT_FLAG_PHYSICAL,
                    'label.mixed' => Basket::FLAG_PRODUCT_MIXED,
                    'label.crowdfunding_digital' => Basket::CONTENT_FLAG_CF_DIGITAL,
                    'label.crowdfunding_shipping' => Basket::CONTENT_FLAG_CF_SHIPPING,
                    'label.crowdfunding_mixed' => Basket::FLAG_CF_MIXED,
                    'label.all_mixed' => Basket::FLAG_MIXED,
                ])
                ->formatValue(function ($value, $entity) {
                    if ($value == Basket::FLAG_MIXED) {
                        return 'Mixed (Digital + Physical + Crowdfunding)';
                    } elseif ($value == Basket::FLAG_DIGITAL_ONLY) {
                        return 'Digital Only (Products + Crowdfunding)';
                    } elseif ($value == Basket::FLAG_NEEDS_SHIPPING) {
                        return 'Requires Shipping (Physical + Crowdfunding)';
                    } elseif ($value == Basket::CONTENT_FLAG_DIGITAL) {
                        return 'Digital Product';
                    } elseif ($value == Basket::CONTENT_FLAG_PHYSICAL) {
                        return 'Physical Product';
                    } elseif ($value == Basket::CONTENT_FLAG_CF_DIGITAL) {
                        return 'Digital Crowdfunding';
                    } elseif ($value == Basket::CONTENT_FLAG_CF_SHIPPING) {
                        return 'Physical Crowdfunding';
                    }

                    return 'Unknown (' . $value . ')';
                }),
            IntegerField::new('total')
                ->setLabel(new TranslatableMessage('label.total', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('shipping')
                ->setLabel(new TranslatableMessage('label.shipping', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('currency')
                ->setLabel(new TranslatableMessage('label.currency', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('quantity')
                ->setLabel(new TranslatableMessage('label.quantity', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            EmailField::new('email')
                ->setLabel(new TranslatableMessage('label.email', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('name')
                ->setLabel(new TranslatableMessage('label.name', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('address')
                ->setLabel(new TranslatableMessage('label.address', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('zip')
                ->setLabel(new TranslatableMessage('label.zip', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('city')
                ->setLabel(new TranslatableMessage('label.city', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('country')
                ->setLabel(new TranslatableMessage('label.country', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('creation')
                ->setLabel(new TranslatableMessage('label.creation', [], 'shop'))
                ->setFormTypeOption('disabled', 'disabled')
                ->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('modification')
                ->setLabel(new TranslatableMessage('label.modification', [], 'shop'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail()
                ->setFormTypeOption('disabled', 'disabled'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        // Paid baskets
        $filterPaid = Action::new('filterPaid', 'Paid', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'paid')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });

        // Validated baskets
        $filterValidated = Action::new('filterValidated', 'Validated', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'validated')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });

        // New baskets
        $filterNew = Action::new('filterNew', 'New', 'fa fa-filter')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[status][value]', 'new')
                    ->set('filters[status][comparison]', '=')
                    ->generateUrl();
            });


        // Send items
        $sendPhysicalItems = Action::new('sendPhysicalItems', 'label.send_items')
            ->linkToRoute('items_shipped', function (Basket $basket): array {
                return [
                    'number' => $basket->getNumber(),
                    'type' => 'product'
                ];
            })
            ->setHtmlAttributes([
                'target' => '_blank',
            ])
            ->displayIf(function (Basket $basket): bool {
                return $basket->getStatus() === 'paid' &&
                    $basket->getNumber() !== null &&
                    ($basket->getContentFlags() & Basket::CONTENT_FLAG_PHYSICAL) &&
                    $basket->getItemsShipped() === null;
            });

        // Send counterparts
        $sendCounterparts = Action::new('sendCounterparts', 'label.send_counterparts')
            ->linkToRoute('items_shipped', function (Basket $basket): array {
                return [
                    'number' => $basket->getNumber(),
                    'type' => 'crowdfunding'
                ];
            })
            ->setHtmlAttributes([
                'target' => '_blank',
            ])
            ->displayIf(function (Basket $basket): bool {
                return $basket->getStatus() === 'paid' &&
                    $basket->getNumber() !== null &&
                    ($basket->getContentFlags() & Basket::CONTENT_FLAG_CF_SHIPPING) &&
                    $basket->getCounterpartsShipped() === null;
            });

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $filterPaid)
            ->add(Crud::PAGE_INDEX, $filterValidated)
            ->add(Crud::PAGE_INDEX, $filterNew)
            ->add(Crud::PAGE_INDEX, $sendPhysicalItems)
            ->add(Crud::PAGE_INDEX, $sendCounterparts)
            ->setPermission(Action::DELETE, $this->configService->get('site-role-needed'))
            ->setPermission(Action::DETAIL, $this->configService->get('site-role-needed'))
            ->setPermission('filterPaid', $this->configService->get('site-role-needed'))
            ->setPermission('filterValidated', $this->configService->get('site-role-needed'))
            ->setPermission('filterNew', $this->configService->get('site-role-needed'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setDefaultSort(['id' => 'DESC'])
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
