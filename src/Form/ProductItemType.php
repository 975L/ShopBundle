<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            ->add('title', TextType::class, [
                'required' => true,
                'label' => 'label.title',
                'attr' => [
                    'placeholder' => 'label.title',
                ],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'label' => 'label.description',
                'attr' => [
                    'placeholder' => 'label.description',
                ],
            ])
            ->add('limitedQuantity', IntegerType::class, [
                'required' => false,
                'label' => 'label.limited_quantity',
                // Left empty is an unlimited stock, and 0 withdraws the item for good: the difference decides whether the sheet says "out of stock" and offers the alert, or says "sold out" and promises nothing
                'help' => 'label.limited_quantity_help',
                'attr' => [
                    'placeholder' => 'label.limited_quantity',
                ],
            ])
            ->add('price', MoneyType::class, [
                'required' => true,
                'label' => 'label.price',
                'divisor' => 100,
                'attr' => [
                    'placeholder' => 'label.price',
                ],
            ])
            ->add('priceBefore', MoneyType::class, [
                'required' => false,
                'label' => 'label.price_before',
                // What the "Omnibus" directive requires of a struck-through price in the EU: the lowest price actually charged over the last 30 days, not a figure chosen to make the offer look better
                'help' => 'label.price_before_help',
                'divisor' => 100,
                'attr' => [
                    'placeholder' => 'label.price_before',
                ],
            ])
            ->add('currency', TextType::class, [
                'required' => true,
                'empty_data' => 'eur',
                'label' => 'label.currency',
                'attr' => [
                    'placeholder' => 'label.currency',
                    'value' => 'eur',
                ],
            ])
            ->add('vat', NumberType::class, [
                'label' => 'label.vat',
                'empty_data' => 0,
                'attr' => [
                    'placeholder' => 'label.vat',
                    'value' => 0,
                ],
            ])
            ->add('sku', TextType::class, [
                'required' => false,
                'label' => 'label.sku',
                'help' => 'label.sku_help',
                'attr' => [
                    'placeholder' => 'label.sku',
                ],
            ])
            ->add('gtin', TextType::class, [
                'required' => false,
                'label' => 'label.gtin',
                'help' => 'label.gtin_help',
                'attr' => [
                    'placeholder' => 'label.gtin',
                ],
            ])
            ->add('itemCondition', ChoiceType::class, [
                'required' => false,
                'label' => 'label.item_condition',
                'help' => 'label.item_condition_help',
                // Nothing selected is a state of its own: the graph then says nothing about the item's condition
                'placeholder' => 'label.item_condition_unstated',
                'choices' => $this->conditions(),
            ])
            ->add('giftCardValue', MoneyType::class, [
                'required' => false,
                'label' => 'label.gift_card_value',
                'help' => 'label.gift_card_value_help',
                // Stored in cents like every other amount of this bundle, typed in the currency the customer reads - same reading as the price above
                'divisor' => 100,
            ])
            ->add('media', ProductItemMediaType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('service', CheckboxType::class, [
                'label' => 'label.service',
                'required' => false,
                'help' => 'label.product_item_service_help',
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'label.published',
                'required' => false,
                'help' => 'label.product_item_published_help',
            ])
            ->add('file', ProductItemFileType::class, [
                'label' => false,
                'required' => false,
                'help' => 'label.product_item_file_help',
            ])
        ;
    }

    // The very tokens the graph knows how to publish, each labelled by its own translation key
    private function conditions(): array
    {
        $conditions = [];

        foreach (array_keys(ProductSnippetBuilder::CONDITIONS) as $token) {
            $conditions['label.item_condition_' . $token] = $token;
        }

        return $conditions;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductItem::class,
            'translation_domain' => 'shop',
        ]);
    }
}
