<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller\Management;

use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Form\CrowdfundingMediaType;
use c975L\ShopBundle\Form\CrowdfundingCounterpartType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

#[IsGranted('ROLE_ADMIN')]
class CrowdfundingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Crowdfunding::class;
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
            MoneyField::new('targetAmount')
                ->setLabel('label.target_amount')
                ->setCurrency('EUR')
                ->setStoredAsCents(true),
            MoneyField::new('currentAmount')
                ->setLabel('label.current_amount')
                ->setCurrency('EUR')
                ->setStoredAsCents(true)
                ->hideOnForm(),
            BooleanField::new('active')
                ->setLabel('label.active'),
            DateTimeField::new('beginDate')
                ->setLabel('label.begin_date'),
            DateTimeField::new('endDate')
                ->setLabel('label.end_date'),
            FormField::addPanel('Media Management')
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class),
            FormField::addPanel('Counterparts Management')
                ->setHelp('Add counterparts to offer to contributors')
                ->hideOnIndex(),
            CollectionField::new('counterparts')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingCounterpartType::class),
            AssociationField::new('contributors')
                ->hideOnIndex()
                ->onlyOnDetail(),
            TextEditorField::new('description')
                ->setLabel('label.description')
                ->hideOnIndex(),
            TextField::new('videoUrl')
                ->setLabel('label.video_url')
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
            ->setDefaultSort(['endDate' => 'DESC'])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
            ->add('active')
            ->add('endDate')
        ;
    }
}