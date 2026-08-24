<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form\Block;

use c975L\ShopBundle\Service\ShopBlockChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecommendationsBlockType extends AbstractType
{
    public function __construct(
        private readonly ShopBlockChoices $choices,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.block_title',
                'required' => false,
            ])
            // Left empty on a product sheet, the block recommends against the product that sheet is about; anywhere else an empty choice has no product to start from and the block renders nothing
            ->add('productSlug', ChoiceType::class, [
                'label' => 'label.block_product',
                'choices' => $this->choices->products(),
                'placeholder' => 'label.block_current_product',
                'required' => false,
                'choice_translation_domain' => false,
            ])
            ->add('max', IntegerType::class, [
                'label' => 'label.block_max_products',
                'required' => false,
                'attr' => ['min' => 1],
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'shop']);
    }
}
