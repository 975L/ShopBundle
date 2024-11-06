<?php
/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Basket;
use c975L\ShopBundle\Form\CoordinatesType;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * ShopFactory class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
class ShopFormFactory implements ShopFormFactoryInterface
{
    public function __construct(
        /**
         * Stores FormFactoryInterface
         */
        private readonly FormFactoryInterface $formFactory
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name, Basket $basket): Form
    {
        switch ($name) {
            case 'coordinates':
                $config = [];
                $type = CoordinatesType::class;
                break;
            default:
                $config = [];
                break;
        }

        return $this->formFactory->create($type, $basket, ['config' => $config]);
    }
}
