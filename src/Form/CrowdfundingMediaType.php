<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use c975L\ShopBundle\Entity\CrowdfundingMedia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CrowdfundingMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', VichFileType::class, [
                'label' => 'label.file',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'label.delete_file',
                'download_uri' => false,
                'imagine_pattern' => 'thumbnail',
                'asset_helper' => true,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'step' => 5
                ]
            ])
            ->add('alt', TextType::class, [
                'label' => 'label.alt_text',
                'required' => false,
                'help' => 'help.image_alt'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingMedia::class,
            'translation_domain' => 'shop'
        ]);
    }
}