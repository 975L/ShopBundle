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
use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Form\CrowdfundingCounterpartType;
use c975L\ShopBundle\Form\CrowdfundingMediaType;
use c975L\ShopBundle\Form\CrowdfundingVideoType;
use c975L\ShopBundle\Form\LotteryType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class CrowdfundingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

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
                ->setLabel(new TranslatableMessage('label.title', [], 'shop')),
            SlugField::new('slug')
                ->setTargetFieldName('title')
                ->hideOnIndex(),
            MoneyField::new('amountGoal')
                ->setLabel(new TranslatableMessage('label.goal', [], 'shop'))
                ->setCurrency('EUR')
                ->setStoredAsCents(true),
            TextField::new('currency')
                ->setLabel(new TranslatableMessage('label.currency', [], 'shop')),
            MoneyField::new('amountAchieved')
                ->setLabel(new TranslatableMessage('label.amount_achieved', [], 'shop'))
                ->setCurrency('EUR')
                ->setStoredAsCents(true)
                ->hideOnForm(),
            DateField::new('beginDate')
                ->setLabel(new TranslatableMessage('label.begin_date', [], 'shop')),
            DateField::new('endDate')
                ->setLabel(new TranslatableMessage('label.end_date', [], 'shop')),
            TextEditorField::new('description')
                ->setLabel(new TranslatableMessage('label.description', [], 'shop'))
                ->hideOnIndex(),

            // Author
            FormField::addFieldset(new TranslatableMessage('label.author', [], 'shop'))
                ->hideOnIndex(),
            TextField::new('authorName')
                ->setLabel(new TranslatableMessage('label.author', [], 'shop')),
            TextEditorField::new('authorPresentation')
                ->setLabel(new TranslatableMessage('label.author_presentation', [], 'shop'))
                ->hideOnIndex(),
            TextField::new('authorWebsite')
                ->setLabel(new TranslatableMessage('label.website', [], 'shop')),
            TextEditorField::new('useFor')
                ->setLabel(new TranslatableMessage('label.use_for', [], 'shop'))
                ->hideOnIndex(),

            // Media management
            FormField::addFieldset(new TranslatableMessage('label.media', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingVideoType::class),

            // Counterpart management
            FormField::addFieldset(new TranslatableMessage('label.counterparts', [], 'shop'))
                ->setHelp(new TranslatableMessage('text.items_management', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('counterparts')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingCounterpartType::class),

            // Lottery management
            FormField::addFieldset(new TranslatableMessage('label.lottery', [], 'shop'))
                ->hideOnIndex(),
            CollectionField::new('lotteries')
                ->hideOnIndex()
                ->setEntryType(LotteryType::class),

            // Dates
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
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, $this->configService->get('site-role-needed'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setDefaultSort(['endDate' => 'DESC'])
        ;
    }
}