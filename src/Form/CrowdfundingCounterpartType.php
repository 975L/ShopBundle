<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CrowdfundingCounterpartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'label.amount',
                'required' => true,
                'divisor' => 100,
                'currency' => 'EUR',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'step' => 5
                ]
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'label.active',
                'required' => false,
            ])
            ->add('isLimited', CheckboxType::class, [
                'label' => 'label.limited_quantity',
                'required' => false,
            ])
            ->add('limitedQuantity', IntegerType::class, [
                'label' => 'label.quantity_available',
                'required' => false,
                'attr' => [
                    'min' => 0
                ]
            ])
            ->add('file', VichFileType::class, [
                'label' => 'label.image',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'label.delete_file',
                'download_uri' => false,
                'imagine_pattern' => 'thumbnail',
                'asset_helper' => true,
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