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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductSearchBlockType extends AbstractType
{
    public function __construct(
        private readonly ShopBlockChoices $choices,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Optional, same as on the other kinds of this bundle: the shop's own index puts the field in its filter row, where a heading would break the row, so a block placed on a page states its own
            ->add('title', TextType::class, [
                'label' => 'label.block_title',
                'required' => false,
            ])
            // Empty searches the whole shop, which is what the search of the shop's own index does
            ->add('categorySlug', ChoiceType::class, [
                'label' => 'label.block_category',
                'choices' => $this->choices->categories(),
                'placeholder' => 'label.block_all_categories',
                'required' => false,
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
