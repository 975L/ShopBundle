<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Email;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The e-mails this bundle sends on its own account, as templates an admin composes rather than Twig files nobody
 * but a developer can touch.
 *
 * Only one so far, and it carries no slot: the back-in-stock alert is all sentences, the item's name and the two
 * links travelling as "{{ }}" placeholders (see ConfigBundle\Service\EmailVerifier for the same shape). Everything
 * about an order is PaymentBundle's, which owns the basket and declares its own six.
 *
 * Every sentence is read from the "shop" catalogue, which is the one place the default wording lives: what
 * c975l:ui:email-templates:ensure seeds, what EmailTemplateRenderer falls back on if the row is deleted, and what a
 * translator writes for a language this bundle does not ship. An admin's rewriting happens after, on the row.
 */
class ShopEmailTemplateProvider implements EmailTemplateProviderInterface
{
    // The name the alert travels under, the one ProductItemStockAlertService asks EmailTemplateRenderer for
    public const string BACK_IN_STOCK = 'back_in_stock';

    // The languages this bundle ships a shop catalogue for. Listed rather than read from kernel.enabled_locales: the translator answers every locale by falling back on the default one, so iterating a site's languages would seed a Spanish row holding French sentences
    private const array LOCALES = ['fr', 'en', 'es'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getEmailTemplates(): array
    {
        $templates = [];
        foreach (self::LOCALES as $locale) {
            foreach ($this->structure($locale) as $name => $blocks) {
                $templates[$name][$locale] = $blocks;
            }
        }

        return $templates;
    }

    /**
     * @return array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>
     */
    private function structure(string $locale): array
    {
        return [
            self::BACK_IN_STOCK => [
                $this->text('label.back_in_stock_intro', $locale, ['%item%' => '{{ item_title }}']),
                $this->text('label.back_in_stock_hurry', $locale),
                ['button', null, null, null, $this->trans('label.back_in_stock_buy', $locale), '{{ product_url }}'],
                $this->text('label.back_in_stock_unsubscribe', $locale, ['%url%' => '{{ unsubscribe_url }}']),
            ],
        ];
    }

    /** @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string} */
    private function text(string $key, string $locale, array $parameters = []): array
    {
        return ['text', null, null, $this->trans($key, $locale, $parameters), null, null];
    }

    // A catalogue parameter becomes the "{{ name }}" an EmailTemplate block substitutes: the two placeholder syntaxes have to meet somewhere, and an admin editing that sentence in the back-office sees the one the editor documents
    private function trans(string $key, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'shop', $locale);
    }
}
