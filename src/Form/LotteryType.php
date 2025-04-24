<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\Lottery;
use Symfony\Component\Form\AbstractType;
use c975L\ShopBundle\Form\LotteryPrizeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class LotteryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isActive', CheckboxType::class, [
                'label' => 'label.enable_lottery',
                'required' => false
            ])
            ->add('identifier', TextType::class, [
                'label' => 'label.lottery_identifier',
                'required' => false,
                'attr' => [
                    'readonly' => true
                ],
            ])
            ->add('drawDate', DateTimeType::class, [
                'label' => 'label.draw_date',
                'required' => false,
                'widget' => 'single_text'
            ])
            ->add('prizes', CollectionType::class, [
                'entry_type' => LotteryPrizeType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'label.prizes',
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lottery::class,
            'translation_domain' => 'shop',
        ]);
    }
}
