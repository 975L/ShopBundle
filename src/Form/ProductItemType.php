<?php

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\ProductItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ProductItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', TextType::class, [
                'label' => 'label.file',
                'attr' => [
                    'placeholder' => 'label.file',
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'attr' => [
                    'placeholder' => 'label.title',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'attr' => [
                    'placeholder' => 'label.description',
                ],
            ])
            ->add('price', IntegerType::class, [
                'label' => 'label.price',
                'attr' => [
                    'placeholder' => 'label.price',
                ],
            ])
            ->add('currency', TextType::class, [
                'label' => 'label.currency',
                'attr' => [
                    'placeholder' => 'label.currency',
                ],
            ])
            ->add('vat', NumberType::class, [
                'label' => 'label.vat',
                'attr' => [
                    'placeholder' => 'label.vat',
                ],
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
