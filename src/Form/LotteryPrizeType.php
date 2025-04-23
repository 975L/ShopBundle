<?php

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\LotteryPrize;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotteryPrizeType extends AbstractType
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
                'attr' => ['rows' => 3],
            ])
            ->add('rank', ChoiceType::class, [
                'label' => 'label.prize_rank',
                'choices' => [
                    'Prize #1 (Grand Prize)' => 1,
                    'Prize #2' => 2,
                    'Prize #3' => 3,
                    'Prize #4' => 4,
                    'Prize #5' => 5,
                ],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotteryPrize::class,
            'translation_domain' => 'shop',
        ]);
    }
}