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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductsBlockType extends AbstractType
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
            // A category deleted or renamed since simply drops out of the list, and the block has to be re-pointed before its page can be saved again - better than silently rendering the whole shop instead of the one category it was set to
            ->add('categorySlug', ChoiceType::class, [
                'label' => 'label.block_category',
                'choices' => $this->choices->categories(),
                'placeholder' => 'label.block_all_categories',
                'required' => false,
                'choice_translation_domain' => false,
            ])
            ->add('max', IntegerType::class, [
                'label' => 'label.block_max_products',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            // Only meaningful together with a maximum: it decides which products of the shop that maximum keeps, the block then declining its own cache entry so the draw is renewed at every render
            ->add('random', CheckboxType::class, [
                'label' => 'label.block_random',
                'required' => false,
            ])
            ->add('displayMore', CheckboxType::class, [
                'label' => 'label.block_display_more',
                'required' => false,
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'shop']);
    }
}
