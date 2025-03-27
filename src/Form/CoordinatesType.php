<?php
/*
 * (c) 2017: 975L <contact@975l.com>
 * (c) 2017: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shop FormType
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
class CoordinatesType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'placeholder.email',
                ],
            ])
        ;
        // Shipping address if not full nemric
        if (false === $options['data']->isNumeric()) {
            $builder
                ->add('name', TextType::class, [
                    'label' => 'label.name',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'placeholder.name',
                    ],
                ])
                ->add('address', TextType::class, [
                    'label' => 'label.address',
                    'attr' => [
                        'placeholder' => 'placeholder.address',
                    ],
                ])
                ->add('city', TextType::class, [
                    'label' => 'label.city',
                    'attr' => [
                        'placeholder' => 'placeholder.city',
                    ],
                ])
                ->add('zip', TextType::class, [
                    'label' => 'label.zip',
                    'attr' => [
                        'placeholder' => 'placeholder.zip',
                    ],
                ])
                ->add('country', TextType::class, [
                    'label' => 'label.country',
                    'attr' => [
                        'placeholder' => 'placeholder.country',
                    ],
                ])
            ;
        }
        // GDPR
        $builder
            ->add('gdpr', CheckboxType::class, [
                'label' => 'text.gdpr',
                'translation_domain' => 'site',
                'required' => true,
                'mapped' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \c975L\ShopBundle\Entity\Basket::class,
            'intention' => 'basket',
            'translation_domain' => 'shop'
        ]);

        $resolver->setRequired('config');
    }
}
