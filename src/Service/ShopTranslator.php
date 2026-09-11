<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\UiBundle\Service\ContentTranslator;

// What this shop says in another language - the words an editor typed on a product, a category, an item and the shop's own line: one row in every language, its translations living beside it rather than in a second thing to sell (see SiteBundle's PageTranslator), and only what a visitor reads, a slug, an SKU, a GTIN, a brand and the invoice being left out. A site declaring a single language never reads any of this, ContentTranslator short-circuiting on isActive()
class ShopTranslator
{
    // The vocabulary this bundle's rows are named with, the way Page and Block name theirs - a plain string, no foreign key ever pointing at it (see UiBundle's Translation)
    public const string OWNER_PRODUCT = 'shop_product';

    public const string OWNER_CATEGORY = 'shop_category';

    public const string OWNER_ITEM = 'shop_item';

    public const string OWNER_SETTINGS = 'shop_settings';

    // What a translation may cover of a product: its name, its description, and the line printed on the gift card it is bought as
    public const array PRODUCT_FIELDS = ['title', 'description', 'giftCardText'];

    public const array CATEGORY_FIELDS = ['name', 'description'];

    // A variant carries its own name and description - "Large, blue" is read beside the product's, not instead of it
    public const array ITEM_FIELDS = ['title', 'description'];

    // The shop's own line above the listing, the only prose the settings row holds that a visitor reads
    public const array SETTINGS_FIELDS = ['intro'];

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    // The languages a row may be written in besides the one it was written in
    /** @return list<string> */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    // Lays the language being rendered over each row's own texts, for the render being built and no longer - called by whatever is about to render them rather than on postLoad, the back office running in the editor's own language and a listing there having to go on showing the text the row was written in
    /** @param iterable<Product|ProductCategory|ProductItem|ShopSettings> $rows */
    public function apply(iterable $rows, ?string $locale = null): void
    {
        // Tested before the collection is touched: on a single-language site the proxy behind it is never initialised, and a listing costs no query at all here
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $rows = $rows instanceof \Traversable ? iterator_to_array($rows) : $rows;

        if ([] === $rows) {
            return;
        }

        $this->preload($rows, $locale);

        foreach ($rows as $row) {
            $id = $row->getId();
            if (null === $id) {
                continue;
            }

            // Given nothing to lay over, translate() hands back the translated fields alone - an untranslated one is absent rather than null, which is what makes the getters fall back on the text the row was written in
            $row->setTranslated($this->contentTranslator->translate($this->owner($row), $id, [], $this->fields($row), $locale));
        }
    }

    // Reads ahead every language of a whole set of rows, so asking translatedLocales() of each of them costs one query per language rather than one per row
    /** @param iterable<Product|ProductCategory|ProductItem|ShopSettings> $rows */
    public function preloadEveryLanguage(iterable $rows): void
    {
        foreach ($this->getTranslatableLocales() as $locale) {
            $this->preload($rows, $locale);
        }
    }

    // Reads ahead a whole set of rows, so a listing of a dozen products costs one query rather than a dozen
    /** @param iterable<Product|ProductCategory|ProductItem|ShopSettings> $rows */
    public function preload(iterable $rows, ?string $locale = null): void
    {
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        // Grouped by kind: each is one query of its own, a product and a category being two owner types
        $ids = [];
        foreach ($rows as $row) {
            $id = $row->getId();
            if (null !== $id) {
                $ids[$this->owner($row)][] = $id;
            }
        }

        foreach ($ids as $owner => $ownerIds) {
            $this->contentTranslator->preload($owner, $ownerIds, $locale);
        }
    }

    // The languages this row really exists in, its own included - a row saying something in a language the moment its own name does, since a description translated under a name that was not is half a sheet. Read through ContentTranslator::values(), preloadEveryLanguage() above reading every language ahead. Nothing calls it yet: it is what an hreflang group will name, a different question from which url answers (ShopTranslatedLocales')
    /** @return list<string> */
    public function translatedLocales(Product | ProductCategory | ProductItem | ShopSettings $row): array
    {
        $id = $row->getId();
        $locales = [$this->siteLocales->getDefaultLocale()];
        if (null === $id) {
            return $locales;
        }

        $name = $this->fields($row)[0];

        foreach ($this->getTranslatableLocales() as $locale) {
            $written = $this->contentTranslator->values($this->owner($row), $id, $locale)[$name] ?? null;
            if (null !== $written && '' !== $written) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    // Every language this row has been given, for the screen that writes them
    /** @return array<string, array<string, string|null>> locale => field => value */
    public function all(Product | ProductCategory | ProductItem | ShopSettings $row): array
    {
        $id = $row->getId();

        return null === $id ? [] : $this->contentTranslator->all($this->owner($row), $id);
    }

    // What a language screen offers for each translatable text: what that language already says, or the source text between brackets where it says nothing yet
    /** @return array<string, string|null> field => value */
    public function promptValues(Product | ProductCategory | ProductItem | ShopSettings $row, string $locale): array
    {
        $written = $this->all($row)[$locale] ?? [];

        $values = [];
        foreach ($this->fields($row) as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($row->getUntranslated($field));
        }

        return $values;
    }

    // Hands what a language screen wrote over to be stored on the flush that saves the row, a field left holding the bracketed source counting as nothing written (see ContentTranslator::stage)
    /** @param array<string, string|null> $values field => value */
    public function stage(Product | ProductCategory | ProductItem | ShopSettings $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null === $id) {
            return;
        }

        $staged = [];
        foreach ($this->fields($row) as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $staged[$field] = ContentTranslator::untouched($values[$field], $row->getUntranslated($field)) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage($this->owner($row), $id, $locale, $staged);
        }
    }

    // Writes a translation straight away rather than staging it - what a seeder or a bulk pass does, having no form to wait for
    /** @param array<string, string|null> $values field => value */
    public function store(Product | ProductCategory | ProductItem | ShopSettings $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null !== $id) {
            $this->contentTranslator->store($this->owner($row), $id, $locale, $values);
        }
    }

    public function owner(Product | ProductCategory | ProductItem | ShopSettings $row): string
    {
        return match (true) {
            $row instanceof Product => self::OWNER_PRODUCT,
            $row instanceof ProductCategory => self::OWNER_CATEGORY,
            $row instanceof ProductItem => self::OWNER_ITEM,
            default => self::OWNER_SETTINGS,
        };
    }

    /** @return list<string> */
    public function fields(Product | ProductCategory | ProductItem | ShopSettings $row): array
    {
        return match (true) {
            $row instanceof Product => self::PRODUCT_FIELDS,
            $row instanceof ProductCategory => self::CATEGORY_FIELDS,
            $row instanceof ProductItem => self::ITEM_FIELDS,
            default => self::SETTINGS_FIELDS,
        };
    }
}
