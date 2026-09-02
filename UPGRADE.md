# UPGRADE Guide

This document describes breaking changes and how to upgrade between major versions.

### v2.5.2

**The basket bar is placed by the site's layout, not by the shop's pages.** `shop/index`, `category/display` and
`product/display` no longer emit it; add PaymentBundle's own component once in the layout instead:

```twig
<twig:c975LPayment:Basket:Navbar/>
```

`Shop:NavbarBasket` is kept as a one-line wrapper around it, so a site that overrode it keeps working - but a page
that emits it beside a layout carrying `Basket:Navbar` gets two bars, the second one never filled by Stimulus.

**`c975l/payment-bundle` is now required in `^6.7`**, which is where `Basket:Navbar` and its `set_timezone` route
arrive. `TimezoneController` is dropped here, the route it answered belonging to whoever owns the basket.

### v2.5.1

**Nothing to do**, beyond upgrading `c975l/payment-bundle` to ^6.6 along with it - the two releases go together,
PaymentBundle asking this bundle where its catalogue is and which template draws its recommendations rather than
naming them itself.

`Shop:ViewButton` is left where it is, for a page that drops it in, but the basket no longer calls it: it draws
PaymentBundle's own `Basket:ContinueShoppingButton`, whose label reads "continue shopping" rather than "shop".

### v2.5

**Articles carry a shipping weight.** One nullable column, and nothing to backfill: an article left unweighed
weighs nothing, which is what every article does today.

```sql
ALTER TABLE shop_product_item ADD weight INT DEFAULT NULL;
```

Generate it with `doctrine:migrations:diff` and run it. **Grams, whole**, as prices are held in cents. Fill it in
for what the shop posts and leave it empty on downloads and services - a line that is not posted is left out of
the weighing whatever the column says.

The weight is read by PaymentBundle through its optional `WeighableBasketItemProviderInterface`, and priced on
the delivery grid it gained in v6.5.0 - **read that bundle's UPGRADE too**: `shop-shipping` is gone, and a shop
that writes no zone posts everything free.

**The structured data changes with it.** A product sheet used to publish the flat rate as its `shippingRate`;
it now publishes what the grid charges for **that article's own weight**, and names the country it is priced for
(`shop-shipping-country`). An article nobody weighed, a shop naming no default country, or a grid saying nothing
about that parcel publishes no `shippingDetails` at all - a rate stated for nowhere, or one tier of a grid stated
as if it covered every parcel, is a guess, and a missing rich result beats a wrong one.

### v2.4

**`isPublished` is replaced by `hidden`, on `Product` and on `ProductItem`.** The switch is the one the other
c975L catalogs carry, and it says the opposite of what the old one did: `hidden = true` is what `isPublished =
false` used to mean. `isPublished()`/`setIsPublished()` are gone - call `isHidden()`/`setHidden()`; so is
`Product::getPublishedItems()`, now **`getVisibleItems()`** (`product.visibleItems` in a template), and
`ProductRepository::findOnePublishedBySlug()`, now **`findOneVisibleBySlug()`** - the lookup every block and every
component naming a product by its slug goes through.

**The migration is not only structural: the data has to be copied over, or every product an admin had taken
down goes on sale.** `doctrine:migrations:diff` writes the ADD and the DROP and nothing in between, so add the
two UPDATEs to the generated migration, in this order:

```sql
ALTER TABLE shop_product ADD hidden TINYINT(1) DEFAULT 0 NOT NULL;
ALTER TABLE shop_product_item ADD hidden TINYINT(1) DEFAULT 0 NOT NULL;
UPDATE shop_product SET hidden = NOT is_published;
UPDATE shop_product_item SET hidden = NOT is_published;
ALTER TABLE shop_product DROP is_published;
ALTER TABLE shop_product_item DROP is_published;
```

A **new** product now starts hidden, where it used to start unpublished - the same thing said the other way
round, so nothing changes for an editor.

The archives keep working: `ProductImportProvider` reads `hidden` when the payload carries it and falls back on
`isPublished` when it doesn't, so an export taken before this version comes back in the state it left.

### v2.2.0

**KnpPaginatorBundle leaves the bundle's dependencies.** `ShopService::findAllProductsPaginated()` and
`ProductService::findAllPaginated()` return CoreBundle's `c975L\UiBundle\Model\Pagination` where they returned
Knp's `PaginationInterface`. The two answer the same figures - `getCurrentPageNumber()`, `getPageCount()`,
`getTotalItemCount()`, `getItemNumberPerPage()`, `route`, `query()` - and are countable and iterable alike, so
`shop/index.html.twig` is untouched and an override of it goes on working. **An app implementing
`ShopServiceInterface` or `ProductServiceInterface` itself, or type-hinting `PaginationInterface` on what they
return, has that type to change.** See CoreBundle's UPGRADE.md for removing the package from the app.

### v2.1.1

**The affinity score is a float column now, which needs a migration.** `ProductAffinity::$affinityScore` was
mapped as `decimal(5, 2)` while the property is typed `float`: Doctrine hands a decimal back as a string, so
`doctrine:schema:validate` reported the mapping as invalid and every reader coerced the value on its way through.
The column becomes a float, the property and the accessors are unchanged, and nothing in your own code has to move:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The scores themselves survive the change - they are percentages `c975l:shop:affinity:calculate` rounds to two
decimals, and the command rewrites them all on its next run anyway.

### v2.0.0

**One new table, which needs a migration.** `shop_product_item_stock_alert` holds the visitors waiting on a
sold-out item: the item, an address, the language they asked in, an unsubscribe token and the date they were told.
Nothing else changes, but the table has to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Two things follow from it. The alert is sent by `c975l:shop:stock-alerts:send`, which
`ShopMaintenanceTaskProvider` schedules hourly — a site running the shared `c975l:config:maintenance:run` has
nothing to add, one running its own crontab has to schedule it. And the e-mail is an `EmailTemplate` named
`back_in_stock`, seeded by `c975l:ui:email-templates:ensure`, so run that command once after upgrading; the health
check reports it as missing until you do.

**A limited quantity is now three states, and the column's default changed.** `ProductItem::$limitedQuantity`
defaults to `null` instead of `0`: empty is an unlimited stock, a cap the orders have not reached is what is left
to sell, a cap they have reached is a shortage the shop expects to end, and `0` withdraws the item for good.
Because `0` used to be the default, every item created without touching the field reads as withdrawn. The column
was already nullable, so nothing changes in the schema and `doctrine:migrations:diff` has nothing to say here —
what the existing rows need is one statement:

```sql
UPDATE shop_product_item SET limited_quantity = NULL WHERE limited_quantity = 0;
```

Run it **before** deliberately withdrawing anything, and skip it if your shop already used `0` to mean withdrawn.
The card's badge follows: `label.out_of_stock` where the shop expects the item back, `label.sold_out` where it
does not. **If you have overridden `Product/Item.html.twig` or `ProductItem/AddButton.html.twig`**, they now say
`label.out_of_stock` where they said `label.sold_out` and `label.limited_quantity_reached`.

**If you have overridden `ProductItem/AddButton.html.twig`**, a sold-out item now carries a second button
offering the subscription, and it appears only where the stock ran out (`orderedQuantity >= limitedQuantity`),
never where the item was withdrawn (`limitedQuantity` at 0) — an item nobody expects back promises nothing. Carry
that branch over, or the feature is reachable only by url.

**`ProductStateServiceInterface` gains two methods.** `isItemAvailable()` and `isItemSoldOut()` state the rule the
card's badge, the add button and the alerts all read, which lived privately in three places. If you have
implemented that interface yourself rather than decorated the service, add them.

**A gift card now has a visual, which needs a migration too.** `Product` gains `gift_card_text` and
`gift_card_scratch`, so run `php bin/console doctrine:migrations:diff` then `doctrine:migrations:migrate` after
the one below. Every existing product carries an empty text and a panel switched on, and an ordinary product is
untouched by both. The visual itself is the product's **first media** and its amounts are its items, so a shop
selling three designs at three prices writes three products of three items rather than nine products.
`ProductBasketItemProvider` copies that visual onto the basket and hands it to PaymentBundle at issuance — see
that bundle's own UPGRADE notes for the four columns it adds to `payment_gift_card`. **If you have overridden
`toBasketData()`**, carry `parent.giftCardText` and `parent.giftCardScratch` with it, or the cards you issue are
printed blank.

**A product item can be a gift card, which needs a migration.** `ProductItem` gains `gift_card_value`,
nullable, holding what a card bought through that item is worth in cents. Every existing item carries none and
goes on being sold exactly as before. Filling it in makes the item a gift card: on payment, one card per unit
bought is issued through PaymentBundle's `GiftCardService`, and the basket entry carries
`Basket::CONTENT_FLAG_GIFT_CARD` on top of the flag saying how it is delivered.

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

This is what raises the `c975l/payment-bundle` requirement to `^6`.

**One new column, which needs a migration.** `ProductItem` gains `is_published`, defaulting to true, so an item
can be taken off sale without being deleted. Every existing item comes back on sale, and a catalogue reads exactly
as it did before, but the column has to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

A site reading a product's items in a template of its own should read `product.publishedItems` rather than
`product.items`, which still holds them all - it is what the back-office edits, what an export carries and what a
copy is made from.

**The shop's index and its category pages are composed in the back-office, which needs a migration.**
`ProductCategory` gains a `shop_product_category_block` join table, and the new single-row `ShopSettings`
entity brings `shop_settings` and `shop_settings_block` - the blocks the shop's index prints above its
listing. Nothing changes for an existing catalogue, which renders no block on either page until one is
placed, but the tables have to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The `ShopSettings` row is created by the dashboard's new **Shop page** screen the first time it is opened.

**`Shop:Shipping` and `Shop:Footer` are gone, and overriding a template is no longer the way to word the
shop.** `<twig:c975LShop:Shop:Shipping/>` and `<twig:c975LShop:Shop:Footer/>` no longer exist, the index,
the category page and the product sheet no longer call them, and `label.shipping`, `label.shipping_free`,
`label.shipping_free_from`, `label.shipping_france_only` and `label.returns_policy` left the three `shop`
catalogs. A site overriding either component keeps a file nothing renders any more: delete it, and say
what it said with a block - on the **Shop page** screen for the index, on the category for a category
page, on the product for a sheet. **The delivery terms and the link to the return policy are not printed
by the bundle any more**, so a shop that shipped goods should compose that band before releasing.

The two bands' rules went with them: `sass/_components.scss` is gone, along with the
`--shop-shipping-color` token. A site painting `.shop-shipping` or `.shop-footer` in its own stylesheet
has nothing left to paint.

**The shop's welcome message is gone.** `<twig:c975LShop:Shop:Information/>` no longer exists, the shop's index
no longer calls it, and `label.welcome_shop` and `label.hope_find_items` left the three `shop` catalogs. A site
overriding `templates/bundles/c975LShopBundle/components/Shop/Information.html.twig` keeps a file nothing renders
any more: delete it, and say what it said with a block on the shop page. An override that also carried an `<h2>`
was printing a second heading over the one `<twig:c975LShop:Product:Products/>` prints itself - that wording
belongs to `label.all_our_items`, which the site can override in its own `translations/shop.<locale>.xlf`.

**One new column, which needs a migration.** `ProductItemDownload` gains `product_item_id`, nullable, so the
customer area hands out the copy already made for an item instead of copying its file again on every visit. The
rows made before it carry none: a fresh copy is made the first time that order is opened, the old one going with the
nightly clean-up.

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**Bought files now show in PaymentBundle's customer area**, `ProductBasketDownloadProvider` implementing its
`BasketDownloadProviderInterface`. Nothing to declare: the service is autoconfigured. A site whose PaymentBundle
predates that contract is unaffected, the provider simply never being asked.

**`ProductItemDownloadServiceInterface` gains `getFileItems()`**, the one walk over a basket's bought files, used
by the emailed links as by the customer area. An implementation of your own must carry it.

**`CardComponent` (`c975LShop:Product:Card`) and `SliderComponent` (`c975LShop:Slider:Slider`) are removed**,
along with `templates/components/Slider/Slider.html.twig`. Both predate the block kinds: a template naming a
product by its slug uses the `shop_product` and `shop_product_slider` kinds, or renders
`components/Product/Product.html.twig` with the product it already holds.

**A new health check, `product-json-ld`**, fetches every product sheet and reports the ones serving no `Product`
structured data or serving some that does not parse. It runs from `c975l:health-check:run` alone, never from a
controller. A site whose sheets are behind a login, or not reachable from the outside, reports them as skipped.

**Requires the matching `c975l/payment-bundle` release.** That bundle's `BasketItemProviderInterface` now has a
provider *hand over* what it needs across the payment instead of stashing it - `onBasketValidated()` returns an
array, `onBasketPaid()` receives it back as a third argument - because `onBasketPaid()` is now also reached from
the payment webhook, which carries no session of the customer. `ProductBasketItemProvider` is aligned on it:
nothing changes for a plain product purchase, which carries nothing across the payment, but **the two bundles
must be upgraded together**. If you implement `BasketItemProviderInterface` yourself, see PaymentBundle's own
UPGRADE for the two signatures.

**The per-purchase copy of a bought file moves from `public/downloads/` to `private/downloads/`.** The copy
itself does not change - same fresh 16-character token in its name, same expiry - but it no longer sits where
the web server can hand it out on its own: `/shop/download/{token}` is the only way to it, and that route now
refuses an expired token itself instead of relying on the nightly command having erased the copy first. On
upgrading, create `private/downloads/`, move or delete what `public/downloads/` still holds, and make sure the
web server does not serve `private/`. **Links already emailed keep their token but no longer find their copy**,
so they fall to the page explaining the link is spent rather than to an error.

**`c975l:shop:downloads:delete` no longer waits for a link to have been clicked.** `ProductItemDownloadRepository::findExpired()`
dropped its `downloaded = true` condition, which left the copy of a link nobody ever opened on the disk for good.
The command also drops the rows expired for more than `ProductItemDownloadService::RETENTION_DAYS` days, which
nothing purged before, and now delegates to the new `ProductItemDownloadServiceInterface::purgeExpired()`.
`ProductItemDownloadServiceInterface` also gains `resolveFilePath()`.

**An overridden `templates/product/item_downloaded.html.twig` should be refreshed.** It now words itself on
whether the link was ever used - `label.download_link_expired` and `text.download_link_expired` are new in the
three `shop` catalogs - and guards the contact button with `route_exists('contactform_display')`, ContactFormBundle
being optional. Without that guard the page fails on a shop installed without it.

**A basket is now revalidated at checkout, and this needs the PaymentBundle version shipping
`BasketItemProviderInterface::validateCheckout()`.** Until then the method sits on
`ProductBasketItemProvider` without ever being called, and nothing changes. Once both are in place, a
basket holding an item that ran out, was withdrawn or whose product was taken offline while it sat there
is refused at the door - the basket untouched, the visitor sent back to it with the provider's own
message - rather than being numbered, charged and left undeliverable. The check `validateAddition()`
could never make is the one that matters most: it is asked one click at a time and knows nothing of what
the basket already holds, so five clicks on an item with one left passed it five times.

**Five new columns and one new join table, which need a migration.** `Product` gains `brand` and the
`shop_product_related` table holding the products an editor picks for it; `ProductItem` gains `price_before`,
`sku` and `gtin`. All of them are nullable and empty on an existing catalogue, which therefore keeps behaving
exactly as it did - a product names no brand, an offer publishes its slug as its `sku` as it already did, and
nothing is struck through until a previous price is typed in. Create them:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**A product holding picked related products no longer shows its calculated recommendations.** The picks replace
the affinity calculation rather than being merged into it, on the sheet as in the `shop_recommendations` block.
A catalogue that picks nothing is unaffected.

**An overridden `Product/Sort.html.twig` should take the new `filters` prop.** Without it, the four order links
drop the price, format and availability the visitor filtered on, and reordering the listing widens it back to
the whole catalogue.

**An overridden `Product/Product.html.twig`, `Product/Item.html.twig` or `Product/Recommendation.html.twig`
shows no struck-through price** until it prints `state.priceBefore` - or `shop_item_price_before(productItem)`
on a sheet row - beside the price it already shows. The badge needs its parameter too:
`state.badge|trans({'%discount%': state.discount}, 'shop')`, `label.discount` being the one key carrying a figure.

**An overridden `product/display.html.twig` or `category/display.html.twig` publishes no breadcrumb** until it
renders `<twig:c975LShop:Shop:Breadcrumb subject="{{ product }}"/>` and the `shop_breadcrumb_json_ld()` script
tag beside the product's own.

**A product is a draft until it is published, and deleting one moves it to a recycle bin - which needs a migration.**
`Product` carries two new columns, `is_published` and `is_deleted`. The first defaults to `1`, so **every product of
an existing catalogue stays online** the day the column is created; a product written from then on starts as a draft,
out of the shop, of the search, of the sitemap, of the recommendations and of every block naming it, its own url
answering 404 until an admin publishes it. Its sheet is read beforehand through the new `product_preview` route
(`/shop/products/{slug}/preview`, admin only). Create the columns:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**The delete action of the products index no longer deletes anything** - it moves the product to the recycle bin,
where it keeps its pictures, its items and its blocks, and where *restore* and *delete permanently* live. Its url
answers 410 while it sits there, and a `gone` Redirect takes that url over for good once it is really deleted.
Renaming a product now writes a 301 from its old url to the new one the same way. **Permanently deleting a product
removes its pictures and its downloadable files from the disk**; links already emailed for those files stop
resolving and show the page explaining the link is spent.

**An overridden `templates/product/display.html.twig` should carry the preview banner.** The template now renders
`label.preview_mode` when `isPreview` is set and the product is not published - without it, an editor previewing a
draft reads a sheet indistinguishable from the public one.

**`Product:Icon` and the icons it drew are gone.** The glyph that marked the kind of an item on the corner of its
picture said less than the row already spelled out, and a digital item now states its file format - `PDF`, `MP3` -
as a badge under its picture once the row is open. A site whose overridden `Item.html.twig` still calls
`<twig:c975LShop:Product:Icon>` drops the call, and one pointing at `bundles/c975lshop/icons/bag-shopping.svg` or
`bundles/c975lshop/icons/file-*.svg` ships those files itself, ShopBundle no longer serving them.

**`label.secure_payments` is gone from the `shop` catalogs.** The secure payment badge of the basket page now
depends on the gateway the `payment-gateway` config names, so PaymentBundle owns its wording and ships
`label.secure_payments_stripe` in its own `payment` catalogs. A site overriding the old key has nothing left to
override - it translates the new one instead.

**The shop takes a test mode, which needs the new config entry to be loaded.** `shop-test-mode` puts a warning
banner on every page of the shop, telling the visitor that nothing is really sold nor shipped, and is switched from
the dashboard's "shop test mode" tile rather than edited by hand. Load it once:

```bash
php bin/console c975l:config:load-all
```

Until now the banner appeared when `stripe-secret` happened to hold the word "test", which said nothing about a
catalog still being filled in and broke the day a live key held it anywhere. It now follows this entry and
PaymentBundle's own `payment-test-mode`, either of the two showing it.

**Product pictures and categories carry two new columns, which needs a migration.** Every media of this bundle
(`shop_media`) has a nullable `alt`, the alternative text a search engine and a screen reader read of a picture,
and `ProductCategory` has a nullable `description`, which is the category page's only content of its own and what
its meta description is built from. Both are empty on an existing catalog and nothing breaks - a picture with no
`alt` renders as before, a category with no description simply shows none - but the columns have to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Fill them in from the back-office: the health check reports how many are still empty, under `mediasWithoutAlt`
and `categoriesWithoutDescription`.

**The category page states its own name.** Its `h1` was `Category: name` and is now the name alone, the shop's
welcome message and its "All our items" heading no longer showing there - both belong to the shop's index. If you
override `templates/bundles/c975LShopBundle/category/display.html.twig`, nothing changes for you.

**Items carry a condition, which needs a migration.** `ProductItem` has a new nullable `item_condition` column,
published in the item's schema.org offer as `NewCondition`, `UsedCondition`, `RefurbishedCondition` or
`DamagedCondition`. An existing catalog is untouched - the column is null everywhere, and a null condition
publishes nothing rather than claiming the item is new - but the column has to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

State it item by item in the back-office to become eligible to Google's merchant listings.

**Products can carry blocks, which needs a migration.** `Product` now implements UiBundle's
`HasBlocksInterface`, its blocks stored in a new `shop_product_block` join table. Nothing changes for an existing
catalog - a product with no block renders exactly as before - but the table has to be created:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**`config/configs.json` declares one key instead of fifteen.** The fifteen it used to declare are all declared
elsewhere now, by whoever reads them: `site-url` and `url-terms-of-use` by ConfigBundle, and the thirteen others -
`shop-name`, `url-terms-of-sales`, the six `shop-email-*`, `shop-currency`, the two `shop-shipping*` and the two
`stripe-*` - by PaymentBundle, which is what prices, ships, charges and e-mails an order. The keys and their values
are untouched in your database and nothing is to be done: `c975l:config:prune` reports no orphan, every one of them
still being declared by an installed bundle. What is left in the file is `shop-test-mode`, which this bundle reads
itself - see the Unreleased notes above.

**The basket and campaign assets have moved to the bundles that draw them.** ShopBundle no longer ships the
basket icons (`minus`, `plus`, `trash-can`, `eye`), the Stripe badge, the smileys or the
calendar icons, nor the SCSS rules for the basket table, the quantity controls and the campaign pages: PaymentBundle
and CrowdfundingBundle each carry their own now, stylesheet included, and register it with UiBundle by themselves.
Nothing to do if you only use the bundles' own components. If one of your templates points at
`bundles/c975lshop/images/...` or `bundles/c975lshop/icons/...` for one of those files, repoint it at
`bundles/c975lpayment/icons/...` or `bundles/c975lcrowdfunding/icons/...`:

```bash
grep -rn 'bundles/c975lshop/' templates/
```

What ShopBundle still serves is its own: `bag-shopping`, `basket-shopping`, `people-group`, the `file-*` type
icons and `no-product-image.webp`.

**`c975l/config-bundle` and `c975l/ui-bundle` are replaced by `c975l/core-bundle`.** The two bundles are now
shipped in a single package, unchanged: same namespaces `c975L\ConfigBundle\` / `c975L\UiBundle\`, same two
entries in `config/bundles.php`, same `@c975LUi/...` template references. Nothing to change in your code - only
what Composer downloads moves:

```bash
composer remove c975l/config-bundle c975l/ui-bundle
composer require c975l/core-bundle
```

The package `replace`s both names, so an outdated bundle still asking for one of them resolves against
core-bundle instead of installing the same namespace twice.

**`c975l/payment-bundle` is now required in `^6`.** The `^5` it used to ask for resolves to the pre-rewrite
package, abandoned since, which pulls `c975l/email-bundle`, `c975l/services-bundle`, `c975l/toolbar-bundle` and
`c975l/site-bundle` in behind it - none of which this bundle uses. The Basket engine products are plugged into
is the v6 one:

```bash
composer require c975l/payment-bundle:^6
```

**The bundle now requires PHP 8.4 and Symfony 8.** It used to declare `"php": ">=8.0"` and `"symfony/*": "*"`, an unbound constraint that let Composer resolve Symfony against whatever PHP the application ran on - so an application on PHP 8.2 silently got Symfony 7 with a bundle only ever tested against Symfony 8. The requirements now say what is actually built and tested: `"php": ">=8.4"` and `"symfony/*": "^8.0"`. If your application is still on Symfony 7, stay on the previous release until you migrate - `composer update` will simply refuse to move rather than break anything.

**Your `App\Entity\User` must now implement `c975L\ConfigBundle\Contract\UserInterface`**, `Product::$user` and `ProductItem::$user` being typed against it instead of `App\Entity\User`. See `c975l/config-bundle`'s own UPGRADE for the one-line change and why nothing else moves - no migration, no configuration, the column and the join stay identical.

### From v1.13 to v2.0

Crowdfunding/Lottery and Payment/Basket/Stripe checkout are no longer part of ShopBundle:

- Crowdfunding/Lottery moved to standalone [c975L/CrowdfundingBundle](https://github.com/975L/CrowdfundingBundle)
- Payment/Basket/Stripe checkout moved to standalone [c975L/PaymentBundle](https://github.com/975L/PaymentBundle)

Both keep the same table names as before, so **no data migration is needed** — just require the
new bundles and remove any direct references to ShopBundle's old `BasketController`,
`CrowdfundingController`, `LotteryController`, `BasketCrudController`, `CrowdfundingCrudController`,
and `PaymentCrudController`, which no longer exist.

The dependency on SiteBundle was also removed: emails now use a self-contained layout instead of
extending SiteBundle's.

```bash
composer require c975l/payment-bundle c975l/crowdfunding-bundle
```

### From v1.9.6 to 1.10

Run this Command **once** when upgrading from v1.9.6 to v1.10 to migrate Product-ProductCategory relationship to ManyToMany:

```bash
php bin/console c975l:shop:migrate-category-many-to-many;
```

Then, run the following commands to update your database schema:

```bash
php bin/console make:migration;
php bin/console doctrine:migrations:migrate;
```
