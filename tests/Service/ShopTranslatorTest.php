<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

class ShopTranslatorTest extends TestCase
{
    // A site declaring a single language reads nothing at all: the row answers what it was written with
    public function testARowIsLeftAloneWhenNothingIsTranslated(): void
    {
        $product = $this->createProduct();

        $this->translator(active: false)->apply([$product]);

        $this->assertSame('Table basse en chêne', $product->getTitle());
        $this->assertSame(['fr'], $this->translator(active: false)->translatedLocales($product));
    }

    // What the language being read says, laid over the row for this render and no longer than that
    public function testTheTranslatedTextsAreTheOnesRead(): void
    {
        $product = $this->createProduct();

        $this->translator(translated: ['title' => 'Oak coffee table'])->apply([$product]);

        $this->assertSame('Oak coffee table', $product->getTitle());
        // Untranslated, so the text the row was written with is what is read - an absent field, not a null one
        $this->assertSame('Un plateau massif.', $product->getDescription());
    }

    // What tells an untouched field from a written one, whatever language is being rendered
    public function testTheRowStillHandsBackTheTextItWasWrittenWith(): void
    {
        $product = $this->createProduct();

        $this->translator(translated: ['title' => 'Oak coffee table'])->apply([$product]);

        $this->assertSame('Table basse en chêne', $product->getUntranslated('title'));
    }

    // A row answers in the languages its own name was written in, its own included: what a localised url is gated on
    public function testARowAnswersInTheLanguagesItsOwnNameWasWrittenIn(): void
    {
        $product = $this->createProduct();

        $this->assertSame(['fr', 'en'], $this->translator(values: ['en' => ['title' => 'Oak coffee table']])->translatedLocales($product));
        $this->assertSame(['fr'], $this->translator(values: ['en' => ['description' => 'A solid top.']])->translatedLocales($product));
    }

    // Each kind is named apart, so a product 12 and a category 12 never read each other's words
    public function testEachKindIsNamedApart(): void
    {
        $translator = $this->translator();

        $this->assertSame(ShopTranslator::OWNER_PRODUCT, $translator->owner(new Product()));
        $this->assertSame(ShopTranslator::OWNER_CATEGORY, $translator->owner(new ProductCategory()));
        $this->assertSame(ShopTranslator::OWNER_ITEM, $translator->owner(new ProductItem()));
        $this->assertSame(ShopTranslator::OWNER_SETTINGS, $translator->owner(new ShopSettings()));
    }

    // A row never saved has no id: nothing to hang a translation on, and nothing to read
    public function testARowWithNoIdReadsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->expects($this->never())->method('all');

        $this->assertSame([], new ShopTranslator($contentTranslator, new SiteLocales(['fr', 'en'], 'fr'))->all(new Product()));
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setTitle('Table basse en chêne')->setDescription('Un plateau massif.');
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 12);

        return $product;
    }

    /**
     * @param array<string, string|null>                $translated what the language being read says
     * @param array<string, array<string, string|null>> $values     locale => field => value, what each language says
     */
    private function translator(bool $active = true, array $translated = [], array $values = []): ShopTranslator
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn($active);
        $contentTranslator->method('getTranslatableLocales')->willReturn($active ? ['en'] : []);
        $contentTranslator->method('translate')->willReturn($translated);
        $contentTranslator->method('values')->willReturnCallback(
            static fn (string $owner, int $id, string $locale): array => $values[$locale] ?? []
        );

        return new ShopTranslator($contentTranslator, new SiteLocales(['fr', 'en'], 'fr'));
    }
}
