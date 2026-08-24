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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductSliderBlockType extends AbstractType
{
    public function __construct(
        private readonly ShopBlockChoices $choices,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Left empty on a product sheet, the slider shows the medias of the product that sheet is about
            ->add('productSlug', ChoiceType::class, [
                'label' => 'label.block_product',
                'choices' => $this->choices->products(),
                'placeholder' => 'label.block_current_product',
                'required' => false,
                'choice_translation_domain' => false,
            ])
            // Milliseconds between two slides, 0 leaving the slider on its arrows alone - the very field UiBundle's own "slider" offers
            ->add('duration', IntegerType::class, [
                'label' => 'label.block_slide_duration',
                'required' => false,
                'attr' => ['min' => 0, 'step' => 500],
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'shop']);
    }
}
