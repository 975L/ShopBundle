<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use c975L\ShopBundle\Form\CrowdfundingCounterpartMediaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class CrowdfundingCounterpartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ]
            ])
            ->add('price', IntegerType::class, [
                'required' => true,
                'label' => 'label.price',
                'attr' => [
                    'placeholder' => 'label.price',
                ],
            ])
            ->add('currency', TextType::class, [
                'required' => true,
                'label' => 'label.currency',
                'data' => 'eur',
                'attr' => [
                    'placeholder' => 'label.currency',
                ],
            ])
            ->add('expectedDelivery', TextType::class, [
                'required' => true,
                'label' => 'label.expected_delivery',
                'attr' => [
                    'placeholder' => 'label.expected_delivery',
                ],
            ])
            ->add('limitedQuantity', IntegerType::class, [
                'label' => 'label.limited_quantity',
                'required' => false,
                'attr' => [
                    'placeholder' => 'label.limited_quantity',
                ],
                ])
            ->add('orderedQuantity', IntegerType::class, [
                'label' => 'label.ordered_quantity',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'label.ordered_quantity',
                    'readonly' => true,
                ],
            ])
            ->add('media', CrowdfundingCounterpartMediaType::class, [
                'label' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingCounterpart::class,
            'translation_domain' => 'shop'
        ]);
    }
}