<?php

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\CrowdfundingNews;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrowdfundingNewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'translation_domain' => 'shop',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'label.content',
                'translation_domain' => 'shop',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingNews::class,
        ]);

         $resolver->setRequired('config');
   }
}