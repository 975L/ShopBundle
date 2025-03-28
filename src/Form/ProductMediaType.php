<?php

namespace c975L\ShopBundle\Form;

use Symfony\Component\Form\AbstractType;
use c975L\ShopBundle\Entity\ProductMedia;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class ProductMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
                'attr' => [
                    'placeholder' => 'label.position',
                ],
            ])
            ->add('file', VichImageType::class, [
                'label' => 'Media',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductMedia::class,
        ]);
    }
}
