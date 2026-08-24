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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductBlockType extends AbstractType
{
    public function __construct(
        private readonly ShopBlockChoices $choices,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('productSlug', ChoiceType::class, [
                'label' => 'label.block_product',
                'choices' => $this->choices->products(),
                'placeholder' => 'label.block_product_placeholder',
                'required' => true,
                'constraints' => [new NotBlank()],
                'choice_translation_domain' => false,
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'shop']);
    }
}
