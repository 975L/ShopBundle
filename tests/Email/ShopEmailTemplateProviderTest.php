<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Email;

use c975L\ShopBundle\Email\ShopEmailTemplateProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * What this bundle seeds into a site's EmailTemplate rows.
 *
 * Built against the real catalogues rather than a stub translator: a mistyped key is not an error anywhere, trans()
 * hands back the key itself, and it would be that string - not the sentence - that gets seeded and then mailed.
 */
class ShopEmailTemplateProviderTest extends TestCase
{
    // The one email this bundle sends on its own account, written in the three languages it ships
    public function testTheAlertIsDeclaredInEveryLanguageTheBundleShips(): void
    {
        $templates = $this->provider()->getEmailTemplates();

        $this->assertSame([ShopEmailTemplateProvider::BACK_IN_STOCK], array_keys($templates));
        $this->assertSame(['fr', 'en', 'es'], array_keys($templates[ShopEmailTemplateProvider::BACK_IN_STOCK]));
    }

    public function testNothingComposedIntoTheAlertIsAnUntranslatedKey(): void
    {
        foreach ($this->provider()->getEmailTemplates() as $name => $blocksByLocale) {
            foreach ($blocksByLocale as $locale => $blocks) {
                foreach ($blocks as [, $heading, , $content, $label]) {
                    foreach ([$heading, $content, $label] as $wording) {
                        $this->assertDoesNotMatchRegularExpression(
                            '/^(label|text)\./',
                            (string) $wording,
                            sprintf('"%s" (%s, %s) holds an untranslated key, which would be mailed as-is', $wording, $name, $locale)
                        );
                    }
                }
            }
        }
    }

    /**
     * The placeholders the sentences carry are exactly the ones the service fills in.
     *
     * A "{{ }}" nobody fills is mailed as itself, and a value nobody placed is dropped in silence: both are only
     * ever noticed by the customer who receives the message.
     */
    public function testEveryPlaceholderIsOneTheServiceFillsIn(): void
    {
        $filled = ['item_title', 'product_url', 'unsubscribe_url'];
        $placed = [];

        foreach ($this->provider()->getEmailTemplates() as $blocksByLocale) {
            foreach ($blocksByLocale as $blocks) {
                foreach ($blocks as [, $heading, , $content, $label, $url]) {
                    preg_match_all('/\{\{\s*([a-z_]+)\s*}}/', $heading . $content . $label . $url, $matches);
                    $placed = [...$placed, ...$matches[1]];
                }
            }
        }

        $placed = array_values(array_unique($placed));
        sort($placed);
        sort($filled);

        $this->assertSame($filled, $placed);
    }

    // Read from translations/, so a catalogue and a declaration cannot drift
    private function provider(): ShopEmailTemplateProvider
    {
        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        foreach (['fr', 'en', 'es'] as $locale) {
            $translator->addResource('xlf', __DIR__ . '/../../translations/shop.' . $locale . '.xlf', $locale, 'shop');
        }

        return new ShopEmailTemplateProvider($translator);
    }
}
