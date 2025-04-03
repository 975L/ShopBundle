<?php

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\ProductItem;
use Symfony\Component\Form\AbstractType;
use c975L\ShopBundle\Form\ProductItemFileType;
use c975L\ShopBundle\Form\ProductItemMediaType;
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
            ->add('title', TextType::class, [
                'required' => true,
                'label' => 'label.title',
                'attr' => [
                    'placeholder' => 'label.title',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'label' => 'label.description',
                'attr' => [
                    'placeholder' => 'label.description',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
                'attr' => [
                    'placeholder' => 'label.position',
                ],
            ])
            ->add('file', ProductItemFileType::class, [
                'label' => false,
                'required' => false,
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
            ->add('vat', NumberType::class, [
                'label' => 'label.vat',
                'data' => 0,
                'attr' => [
                    'placeholder' => 'label.vat',
                ],
            ])
            ->add('media', ProductItemMediaType::class, [
                'label' => false,
                'required' => false,
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
