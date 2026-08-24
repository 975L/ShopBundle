<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Form;

use c975L\UiBundle\Service\FormBotProtection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

// The one field somebody waiting on an item fills in. Not bound to the entity: the item and the locale are the page's own business, and nothing a form posts should be able to name a row
class ProductItemStockAlertType extends AbstractType
{
    public function __construct(
        private readonly FormBotProtection $formBotProtection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => t('label.stock_alert_email', [], 'shop'),
                'help' => t('label.stock_alert_email_help', [], 'shop'),
                // The 100 characters ProductItemStockAlert stores, which is what PaymentBundle's Basket gives its own address column
                'constraints' => [new NotBlank(), new Email(), new Length(max: 100)],
            ])
        ;

        // The same honeypot the other public forms of the ecosystem carry, rather than one of its own
        $this->formBotProtection->addHoneypotField($builder, $this->requestStack->getCurrentRequest());
    }
}
