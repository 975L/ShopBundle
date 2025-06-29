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
use Symfony\Component\Form\AbstractType;
use c975L\ShopBundle\Form\ProductItemFileType;
use c975L\ShopBundle\Form\ProductItemMediaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ProductItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                'label' => 'label.limited_quantity',
                'attr' => [
                    'placeholder' => 'label.limited_quantity',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
                'attr' => [
                    'placeholder' => 'label.position',
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
            ->add('media', ProductItemMediaType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('service', CheckboxType::class, [
                'label' => 'label.service',
                'required' => false,
                'help' => 'label.product_item_service_help',
            ])
            ->add('file', ProductItemFileType::class, [
                'label' => false,
                'required' => false,
                'help' => 'label.product_item_file_help',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductItem::class,
        ]);
    }
}
