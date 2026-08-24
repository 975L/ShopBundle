# ShopBundle

Symfony bundle for e-commerce on the c975L core — product catalog with categories, media, downloadable files and affinity recommendations. Checkout is delegated to [c975L/PaymentBundle](https://github.com/975L/PaymentBundle).

[![GitHub](https://img.shields.io/github/license/975L/ShopBundle)](https://github.com/975L/ShopBundle/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/shop-bundle)](https://packagist.org/packages/c975l/shop-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/shop-bundle)](https://packagist.org/packages/c975l/shop-bundle)
[![Codacy Grade](https://app.codacy.com/project/badge/Grade/12a75ecfc03748c5a72a631010794932)](https://app.codacy.com/gh/975L/ShopBundle/dashboard)

> **BUNDLE UNDER DEVELOPMENT — USE AT YOUR OWN RISK**

## Why ShopBundle

![ShopBundle](.github/images/ShopBundle.svg)

Add ShopBundle on top of the [c975L core](https://github.com/975L/CoreBundle) - ConfigBundle and UiBundle in a single package - and get a product catalog with EasyAdmin management — categories, media, downloadable items, affinity recommendations. Checkout goes through [c975L/PaymentBundle](https://github.com/975L/PaymentBundle)'s Basket engine; crowdfunding/lottery live in [c975L/CrowdfundingBundle](https://github.com/975L/CrowdfundingBundle): both rest on the same foundation, alongside ShopBundle, never on top of it.

> [!WARNING]
> Since **v2.0**, Crowdfunding/Lottery and Payment/Basket/Stripe checkout are no longer part of
> ShopBundle — they moved to standalone [c975L/CrowdfundingBundle](https://github.com/975L/CrowdfundingBundle)
> and [c975L/PaymentBundle](https://github.com/975L/PaymentBundle). Same table names, no data migration
> needed. See [UPGRADE.md](UPGRADE.md) before upgrading.

---

> **TL;DR** — A product catalog (categories, media, downloadable items, affinity recommendations) managed from EasyAdmin. Checkout isn't here: products are plugged into PaymentBundle's Basket engine through `BasketItemProviderInterface`, and crowdfunding/lottery moved to CrowdfundingBundle in v2.0.

## Contents

- **Setup** — [requirements](#requirements) · [installation](#installation) · [upgrading from v1](#upgrading)
- **Using it** — [usage](#usage) · [block kinds](#block-kinds) · [composing the shop](#composing-the-shop) · [commands](#commands) · [export / import products](#export--import-products) · [sitemap, llms.txt and health check](#sitemap-llmstxt-and-health-check) · [structured data](#structured-data) · [wish list](#wish-list) · [back-in-stock alerts](#back-in-stock-alerts) · [customer ratings](#customer-ratings) · [test mode](#test-mode) · [what the site's dashboard gets](#what-the-sites-dashboard-gets)

## Features

- Customer ratings on the product sheet, behind one setting - UiBundle's widget, one vote per visitor without a login (see [customer ratings](#customer-ratings))
- Wish list on the sheet and on every card, behind one setting - UiBundle's own list on `/favorites`, following a signed-in customer from one device to the next (see [wish list](#wish-list))
- Back-in-stock alerts on a sold-out item, taken by email and sent hourly in batches, each carrying its own
  unsubscribe link (see [back-in-stock alerts](#back-in-stock-alerts))
- Product catalog with categories, media, and downloadable items
- Shop index, category pages and product sheets composed in the back-office with UiBundle's blocks, no template of your own
- Eight block kinds of its own, putting the catalog on any page of the site
- `Product` structured data (JSON-LD), the only `offers` node of the c975L ecosystem
- `ItemList` structured data on the shop's index and its category pages
- Plugs products into PaymentBundle's Basket/checkout engine via `BasketItemProviderInterface`
- Product cards stating their own price, formats and availability, read from the items rather than stored
- Struck-through price and discount badge, on the card as on the sheet, guarded against a figure that no longer holds
- Listing ordered by novelty or price and narrowed by price, format and availability, growing on scroll, mobile first from the phone up
- Breadcrumb above every sheet and category page, published as `BreadcrumbList` structured data
- Product affinity calculation and recommendations, overridden by the products an editor picks by hand
- Sitemap, `llms.txt` section and health check, all from one provider
- Catalogue and deliveries checked on their own weekly, `shop-integrity`: a file paid for and never handed over, a file on sale that left the server, an article sold past its stock
- Shop pages selectable as SiteBundle menu targets
- Products and categories exported/imported as a zip (pictures, items with their paid files, sheet blocks and categories bundled in), plugging into ConfigBundle's **Export sync (everything)** dashboard shortcut and **Import content** screen
- Media directories declared for backup, order backlog and catalog gaps reported to the status report
- Alternative text on every product picture, description of its own on every category
- EasyAdmin CRUD for products, written as drafts, ordered by dragging the index rows, duplicated with everything they hold, kept in a recycle bin once deleted, and exported as SQL/CSV/JSON from their index
- Gift cards sold as an ordinary item, one card issued per unit once the order is paid, its visual built in the
  back-office and copied onto the card (see [gift cards](#gift-cards))
- Test mode switched from the dashboard, warning every visitor that nothing is really sold
- Four skills written for the coding agents of the sites installing this bundle, shipped in the package and read straight from `vendor/`

---

## Requirements

- PHP >= 8.4
- [c975L/CoreBundle](https://github.com/975L/CoreBundle) — ConfigBundle and UiBundle in a single package; UiBundle
  provides `Entity\Trait\VichMediaTrait`, used by this bundle's own `Media` (Doctrine `SINGLE_TABLE` base for
  Product/ProductItem uploads) — fully independent from CrowdfundingBundle's own separate media hierarchy, no
  dependency between the two
- [c975L/PaymentBundle](https://github.com/975L/PaymentBundle) — Basket/checkout engine
- Doctrine ORM
- EasyAdmin
- KNP Paginator Bundle
- Imagine
- symfony/ux-live-component
- VichUploader Bundle

---

## Installation

### Download

```bash
composer require c975l/shop-bundle
```

### Run migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Create required directories

```bash
mkdir -p public/medias private/downloads
```

Add `public/medias/` and `private/` to your `.gitignore`. Bought files are copied per purchase into
`private/downloads/`, which the web server must **not** serve - the download route hands them over itself.

A buyer reaches those files twice: by the link emailed once the order is paid, valid for seven days, and from
PaymentBundle's customer area, where `ProductBasketDownloadProvider` hands out those very links for as long as
they live - the page and the email promise the same thing, and the nightly purge takes the copies away together.

### Load configuration values

This bundle declares three configuration keys of its own — `shop-favorites` (see [wish list](#wish-list)),
`shop-rating` (see [customer ratings](#customer-ratings)) and `shop-test-mode` (see [test mode](#test-mode)). It
also reads `site-url`, `site-role-admin` and `site-role-editor`, all three declared by ConfigBundle, and the
shop's e-mail and terms-of-sales keys are declared by
[c975L/PaymentBundle](https://github.com/975L/PaymentBundle), which is what sends and reads them. One command
loads them all:

```bash
php bin/console c975l:config:load-all
```

### Enable routes

Add to `config/routes.yaml`:

```yaml
c975_l_shop:
    resource: "@c975LShopBundle/src/Controller/"
    type: attribute
    prefix: /
```

This bundle has no Stimulus controllers of its own — the basket add/remove UI is registered by
[c975L/PaymentBundle](https://github.com/975L/PaymentBundle) (see its README).

---

## Usage

| URL | Description |
| --- | --- |
| `/shop` | Shop front page, `?order=newest\|price_asc\|price_desc` ordering the listing |
| `/shop/terms-of-sales` | Terms of sales, unless SiteBundle is installed (see below) |
| `/shop/products/{slug}/preview` | The sheet of a product not published yet (requires `ROLE_ADMIN`) |
| `/shop/management` | EasyAdmin management (requires `ROLE_ADMIN`) |

The catalogue's own order is set by dragging the rows of the products index by their `position` cell, rather than by
typing a number into every product it shifts (UiBundle's `ea-index-sort.js`, the same drag as the block collections).
The index is paginated: a page dropped in a new order takes back its own slots among the whole catalogue, so the pages
before it keep theirs. A product's own items and pictures reorder the same way, by dragging their rows in its edit form.

A product is a draft until it is published: it stays out of the catalogue, of the search, of the sitemap, of the
recommendations and of every block naming it, and its own url answers 404. The *preview* action opens its sheet as
the visitor will read it, uncached and behind the admin role, so a product is written, composed and checked before
anyone can see it. Publishing is the deliberate act, a switch on the index and on the edit form.

An item has one switch of its own: **published**, unticked on the item's row in the product's edit form. An item
taken off sale leaves the sheet, the price and the formats its card states, the offers of the structured data and
the basket - while keeping its file, its picture and its stock, one tick away from coming back. There is no recycle
bin beside it: what has no url of its own has no address to answer 410 for, so an item nobody wants back is deleted
outright, with its picture and its downloadable file.

Deleting a product moves it to the **recycle bin**, reached from the button above the index: it keeps its pictures,
its items and its blocks, and its url answers 410 for as long as it can still be restored. Restoring brings it back
as a draft, to be read once before it goes online again. *Delete permanently* is the only thing that actually
removes it, from that view alone, and takes its pictures and its downloadable files with it.

Both moves leave the urls behind them in order (ConfigBundle's `Redirect`): renaming a product writes a 301 from its
old url to the new one, deleting it for good writes a `gone` row answering 410 there for ever - which a search engine
acts on far faster than the 404 that url would otherwise fall back to. A redirect an admin had set up towards that
product becomes a `gone` row too rather than dangling, and a path already covered by a redirect keeps its own target.

A product is duplicated from its index row or from its own edit screen: the copy takes its pictures, its items with
their own picture and downloadable file, its categories and the blocks composing its sheet, each file copied on the
disk under a name of its own rather than shared with the original. Nothing is sold of the copy - the items' ordered
quantity is reset - and the copy is a draft, opened straight away to be renamed and priced.

Restrict access in `config/packages/security.yaml`:

```yaml
security:
    access_control:
        - { path: ^/shop/management, roles: ROLE_ADMIN }
```

---

## Shop listing and product sheet

Both public templates are mobile first: a single column is the default, the grids widening on their own through
`auto-fill` / `minmax()`, and the few media queries only raise what a phone cannot hold.

The listing shows, above the products, how many the shop holds and in how many categories, then one row of
filters - category selector, live search and the three orders `?order=` accepts. An order the listing does not
offer falls back on the shop's own positions.

A second row narrows the listing on what only the items know: `?price=`, `?format=` and `?stock=`. They are a
plain GET form with a submit button rather than a live one - this bundle ships no javascript, and a row that
only filtered on change would leave whoever has none unable to filter at all. The order the visitor came with
travels through them, and through the "load more" link, in both directions.

| Parameter | Values |
| --- | --- |
| `?price=` | `min-max` in **cents**, upper bound exclusive, left empty on the open-ended last band (`6900-`) |
| `?format=` | `physical`, `digital` or `service` |
| `?stock=` | `available`, which is what the card itself calls in stock: nothing sold out, no release still ahead |

The price bands offered are cut from the catalogue's own dearest item rather than written out, so they suit
the shop they are offered on rather than one shop in particular. A range is read as a range and not checked
against the bands currently offered, so a shared url keeps working once a dearer product has moved them.

**The three of them are on `/shop` only.** A category page lists what its category holds, in the shop's own
order and without pagination of its own, so there is nothing there for them to narrow.

The products come twelve at a time and the listing grows as the visitor scrolls, through UiBundle's
`infiniteScroll` controller: it fetches the page the "Voir plus de produits" link already points to and appends
its cards, the footer counting what is on screen out of the whole. No route and no partial template answer for
it - that link carries the order, the category and the search the visitor came with, and without javascript, or
for a crawler, it is the ordinary link to the next page it looks like.

Each card states what the product is worth and whether it can be bought, all of it read from its **published**
items by `ProductStateService` rather than stored on the product:

| Shown | Read from |
| --- | --- |
| Price, and "from" when the items differ | the lowest `ProductItem.price` |
| Struck-through price and "-20 %" | `ProductItem.priceBefore` of that same cheapest item |
| Formats - physical, digital, service | `ProductItem.service` and `ProductItem.file` |
| "Coming soon" | `Product.availableAt` still ahead |
| "Sold out" | not one item still orderable |
| "Limited quantity" | at least one `ProductItem.limitedQuantity` above zero |
| "Free" | a lowest price of zero |
| "New" | `Product.creation` within the last 30 days |

A `priceBefore` that is not **above** the price is ignored everywhere - on the card, on the sheet and in
the structured data - so a value left behind by an offer that ended never advertises a cut of zero or
less, and a cut too small to round up to one whole percent shows no badge rather than "-0 %". The
figure itself is nobody's to compute: in the European Union the "Omnibus" directive requires it to be
the lowest price actually charged over the last 30 days, which the field's help text states and no
column can enforce.

The state is exposed to Twig the same way the structured data is, so overriding the card in your app keeps its
badges and its price:

```twig
{% set state = shop_product_state(product) %}
{{ state.badge ? state.badge|trans({}, 'shop') : '' }}
{{ shop_item_format(productItem)|trans({}, 'shop') }}
```

On the product sheet, the pictures sit beside the decision from 900px up and the items are rows only one of
which opens at a time - HTML's own exclusive accordion through `<details name="product-items">`, so the bundle
ships no JavaScript for it. Quantity is added one click at a time by PaymentBundle's `basket` controller, which
also fills the basket bar at the bottom of the page.

An editor browsing the shop is offered UiBundle's own hover button on what they are looking at, exactly as on a
page's blocks: a card on the shop's index or on a category page leads to the product it stands for, a category's
own text to the field it is printed from, and on a sheet the gallery, the description and the items each to
theirs, the blocks composed on it to their own row. Nobody else ever sees it, `site-role-editor` deciding it,
and the URLs are generated rather than written out - `shop_product_edit_url(product, field)` and
`shop_product_category_edit_url(category, field)` for the objects and their fields, `ShopBlockEditUrlProvider`
for the blocks. A card rendered by a block kind carries none: its html is stored in the block cache, shared by
every visitor, and the block's own button already leads there.

```twig
{% set editUrl = is_granted(config('site-role-editor')) ? shop_product_edit_url(product, 'medias') : null %}
```

---

## Breadcrumb

A product sheet and a category page each open with the trail leading to them - the shop, the product's first
category, the page itself - and publish that same trail as `BreadcrumbList` structured data, which is what a
search engine prints in its results in place of the raw url.

```twig
<twig:c975LShop:Shop:Breadcrumb subject="{{ product }}"/>
```

`ShopBreadcrumbBuilder` resolves the levels once and both read them: the component above the page, and
`shop_breadcrumb_json_ld()` in its own `<script>` tag beside the product's graph. That is what keeps the
markup from claiming a trail the visitor is not shown, and a trail of fewer than two levels publishes nothing.

The trail starts at the shop and **not** at the site's home page: that route belongs to whichever bundle draws
the site, and this bundle runs without any of them.

---

## Terms of sales

Selling online means publishing terms of sales, and the document is this bundle's, not the payment layer's.
UiBundle ships the `france/terms-of-sales` model in English, French and Spanish, and there are two ways of
serving it:

- **With [c975L/SiteBundle](https://github.com/975L/SiteBundle)**: nothing to do.
  `c975l:site:pages:import-defaults` already creates the "Terms of sales" page holding a `legal_model` block,
  which the client then customizes section by section from the back-office.
- **Without it**: `/shop/terms-of-sales` renders the model as the bundle ships it. No customization screen
  goes with it, a customization being stored on a block and there being no page to hold one.

The route serves the document only when SiteBundle is absent, and answers `404` otherwise: the page and the
route render the same model, but the page carries whatever the client rewrote in it, and one site publishing
two different contracts is the failure worth preventing.

Either way, point `url-terms-of-sales` at whichever URL serves it — that key is declared and read by
[c975L/PaymentBundle](https://github.com/975L/PaymentBundle), which links to it on the payment form.

---

## Block kinds

The bundle registers nine kinds with UiBundle's `BlockRegistry`, all in the **Shop** category, so the catalog
reaches any page composed in the back-office - a SiteBundle `Page`, a `Book`, a product sheet - without a template
of your own.

| Kind | What it shows | Form type | Template |
| --- | --- | --- | --- |
| `shop_products` | A grid of products, limited to a category or not, optionally drawn at random | `ProductsBlockType` | `blocks/Products.html.twig` |
| `shop_categories` | The links to the shop's categories | `CategoriesBlockType` | `blocks/Categories.html.twig` |
| `shop_product` | One product, as a card linking to its sheet | `ProductBlockType` | `blocks/Product.html.twig` |
| `shop_product_button` | A button whose label and link follow the product | `ProductButtonBlockType` | `blocks/ProductButton.html.twig` |
| `shop_search` | The shop's live search, under a heading of its own when given one | `ProductSearchBlockType` | `blocks/Search.html.twig` |
| `shop_recommendations` | The products bought with this one, from the calculated affinities | `RecommendationsBlockType` | `blocks/Recommendations.html.twig` |
| `shop_product_items` | A product's buyable items, as rows only one of which opens at a time | `ProductItemsBlockType` | `blocks/ProductItems.html.twig` |
| `shop_product_slider` | A product's medias, in UiBundle's slider | `ProductSliderBlockType` | `blocks/ProductSlider.html.twig` |
| `shop_gift_cards` | The cards the shop sells: one visual per card, and under each the amounts it is bought for | `GiftCardsBlockType` | `blocks/GiftCards.html.twig` |

> **Maintenance note:** update this table whenever a kind is added, renamed, or removed in `config/services.yaml`.

A block stores what to show - a category slug, a maximum - never the products themselves, which `ShopBlockExtension`
resolves live at render time through the `shop_block_*()` Twig functions. So a block never goes stale against the
catalog, and a product renamed or deleted leaves the blocks pointing at it rendering nothing rather than half a card.

### The three kinds of a product sheet

`shop_recommendations`, `shop_product_items` and `shop_product_slider` accept **no product at all**: left empty, they
show the product of the sheet they sit on, read from the current route. The last two declare the `shop_product`
context, which `ProductCrudController` passes to its blocks collection - they are offered on a product sheet and on
a container's slots, and nowhere else, having no product to read anywhere else.

That is what lets a sheet be composed entirely in the back-office: the buy table sits where the editor puts it,
rather than always after every block.

Placed on a sheet, each of the three **takes over the hardcoded section it replaces**: `product/display.html.twig`
reads the sheet's kinds through `shop_block_sheet_kinds()` - a container's slots included - and renders its own
slider, buy table or recommendations only when no block of that kind is there. So the sheet keeps working untouched,
and yields section by section as the editor places the blocks.

A context only filters a blocks collection that declares one - SiteBundle's `Page` does, with `page`. A collection
declaring none still offers every pickable kind, those two included; dropped there they render nothing, having no
product to read.

### Block showcase

`ShopShowcaseProvider` renders the nine kinds for a block showcase page (see UiBundle's `GalleryShowcaseRegistry`).
None of them fits `BlockFixtureProviderInterface` - their templates query the catalog live - so each one is rendered
here against the same components, with stand-in data instead of those queries.

**It needs no media file of its own.** The images come from whatever the hosting site already declares through
`PlaceholderMediaProviderInterface`; the slider is handed the real `Media` entities `BlockFixtureMediaAttacher::nextPlaceholderImage()`
builds from those paths, which is what `vich_uploader_asset()` reads. A site declaring no placeholder image simply
gets no shop showcase.

### Render cache

Every kind but `shop_search` is `cacheable: true`. Their entries carry the catalog tags `ShopBlockCacheTagProvider`
applies, and `ShopCacheInvalidationListener` drops those tags whenever a `Product`, a `ProductItem`, a `ProductMedia`,
a `ProductCategory` or a `ProductAffinity` changes - the gap UiBundle's own `BlockCacheInvalidationListener` cannot
close, knowing about the changed `Block` alone. `c975l:shop:affinity:calculate` invalidates them too: its bulk
`DELETE` fires no Doctrine event.

Two exceptions:

- `shop_search` renders a Live Component, whose markup carries the props checksum and the csrf token of the current
  session - the very reason UiBundle's own `form` kind isn't cached either.
- a `shop_products` block drawing **at random** declines its own entry, a cached one freezing the draw until the
  catalog itself changes.

---

## Composing the shop

The three pages of the shop carry blocks, so what the shop says of itself - a delivery band, a welcome message,
arguments, a gallery, a FAQ - is composed in the back-office with the block kinds UiBundle ships, and there is no
template of yours to write nor component of this bundle to override:

| Page | Owner | Edited in | Rendered by |
| --- | --- | --- | --- |
| `/shop` | `ShopSettings`, a single row | the dashboard's **Shop page** screen | `shop/index.html.twig`, above the listing |
| a category | `ProductCategory` | the category's own CRUD screen | `category/display.html.twig`, under its description |
| a product sheet | `Product` | the product's own CRUD screen | `product/display.html.twig`, around its items |

Each of them renders the same component:

```twig
<twig:c975LUi:Blocks:Blocks blocks="{{ product.blocks }}"/>
```

A block can be dragged from one of the three to another, `ShopBlockOwnerResolver` telling UiBundle's move screen
which one a block belongs to - `product`, `product_category` or `shop`. The front-end "Edit this block" button
leads back to whichever screen composes it, `ShopBlockEditUrlProvider` resolving it for the three.

The single `ShopSettings` row is created the first time the **Shop page** screen is opened: a shop that never
opened it renders no block above its listing rather than failing on a row that was never created.

The blocks live in the `shop_product_block`, `shop_product_category_block` and `shop_settings_block` join tables,
so an existing installation needs a migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## Related products

The recommendations under a sheet are calculated from what has actually been bought together
(`ProductAffinity`, see the commands below) - which says nothing at all until something has been sold, and
that is exactly when a new catalogue needs cross-selling most.

So a product also carries the products an editor picked for it, chosen in its own CRUD screen. **When it holds
any, they replace the calculation entirely** rather than being merged into it, and only the ones the shop is
standing behind are kept - a draft or a trashed pick is dropped, as it is from every other block naming it.
The relation is deliberately one-way: a case goes with a phone, where the phone leads on its own.

The sheet and the `shop_recommendations` block both read `ProductRecommendationService::getSimilarProducts()`,
so they can never show two different sets. The picks live in a `shop_product_related` join table.

---

## Commands

| Command | Description |
| --- | --- |
| `php bin/console c975l:shop:downloads:delete` | Deletes expired download copies and the rows kept past them |
| `php bin/console c975l:shop:affinity:calculate` | Calculates product affinities |
| `php bin/console c975l:shop:stock-alerts:send` | Tells the visitors waiting on an item that it is back, `--limit` alerts at a time (50 by default) |

`ShopMaintenanceTaskProvider` schedules all three through the Symfony Scheduler, so a site installing this bundle has nothing to add to its own schedule and needs no system crontab entry.

---

## Export / import products

Selected products can be exported as a zip via the product index's "Export selection" batch action, meant
to be re-uploaded on another site/environment through ConfigBundle's **Import content** dashboard screen
(see `ProductImportProvider`). Ids never need to match between the two sites: a product is matched by slug
on import, an item by its slug within its product, a category by its own slug. `ProductExportProvider` (the
same serialization, every product) also plugs the catalogue into ConfigBundle's **Export sync (everything)**
dashboard shortcut, `ProductCategoryExportProvider` doing the same for the categories.

A product travels whole: its pictures, its items with the picture and the **file they are bought for**, its
[sheet composition](#composing-the-shop) with the medias its blocks hold, its categories and the
[related products](#related-products) an editor picked. Drafts and the recycle bin travel too, each product
carrying its own published and trashed flags: a sync mirrors its source rather than publishing what an admin
had taken down.

Unlike the flat SQL/CSV/JSON dumps of the same index, which carry one table at a time, this is the archive
that carries a product with everything hanging off it.

**Files travel with their names**, and are laid straight back under them: the upload pipeline is skipped
entirely, so a synced catalogue answers at the very same image urls on every site it is carried to, and
importing three hundred pictures copies files instead of resizing three hundred images. A name coming out of
an archive is only honoured under `medias/shop/` (below `public/`, and below `private/` for the paid files),
as a plain relative name: anything climbing out of it is refused and the file named by Vich instead, as an
archive exported before the names travelled is. A name another product already holds falls back the same way,
the column being unique site-wide.

**An import never deletes what it cannot put back.** A product the archive doesn't name is left alone, and so
is an item: what a customer has paid for is not re-uploadable, and an item still hanging off a paid basket
has its file named by the download link (see `ProductItemDownload`). A product's own pictures are mirrored,
being one drag to upload again. Nothing is deleted from a product either when the archive holds no bytes for
its item's picture or file - the placeholder such an item carries is read as "nothing to say", not as "empty
it".

Two things are deliberately left out of the archive, both being rebuilt rather than carried: the affinities
between products, which `c975l:shop:affinity:calculate` recomputes from the orders of the site they belong
to, and the download links of the baskets, which belong to the payments rather than to the catalogue.

---

## Sitemap, llms.txt and health check

`ShopSitemapProvider` (ConfigBundle's `SitemapProviderInterface`) declares the shop, its products and its
categories. Its product and category urls also carry the optional `title`/`description` keys of that contract,
which the sitemap ignores and from which ConfigBundle's `SeoFilesWriter` builds the `## Shop` section of the
site's `public/llms.txt`. The shop's own `/shop` url deliberately carries no title, so it stays out of that index:
a heading pointing at the catalog's front door tells a language model nothing it can use.

Those same urls are also **health-checked** for free, ConfigBundle registering one check per sitemap provider: every declared url gets the content-quality checks (title/description length, missing `<h1>`, Open Graph share tags, images without `alt`, broken links) under its own `urls-shop` kind on the Health check dashboard, schedulable apart from the rest:

```bash
php bin/console c975l:health-check:run --kind=urls-shop
```

Nothing to implement for any of the three: declaring the sitemap is the whole contract.

A second check of its own, `product-json-ld`, reads every product sheet the way a search engine does and reports
what it finds there: no structured data at all, a block that does not parse, or one that parses without
describing a `Product`. It is the counterpart of writing that markup — the builder being right proves nothing
about a site whose own template stopped calling `product_json_ld()`:

```bash
php bin/console c975l:health-check:run --kind=product-json-ld
```

A third one, `shop-integrity`, looks for what a shop cannot see about itself — the counterpart of PaymentBundle's
`basket-integrity`, which reads the orders themselves where these four need the catalogue resolved:

| Row | What it reports |
|---|---|
| `#undelivered-downloads` | a paid order holding a file whose copy was never made nor sent - read only as far back as a link lives, the nightly purge taking the copies of older orders away |
| `#missing-files` | a file on sale that is no longer on the server: the sheet still offers it, the checkout still takes the money, and the delivery skips the item rather than failing |
| `#oversold-items` | an article ordered more times than the stock declared |
| `#free-items` | an article on sale for nothing - a warning and never an error, and reported only where it is the exception: a catalogue giving away more than it sells has that row skipped |

```bash
php bin/console c975l:health-check:run --kind=shop-integrity
```

Each row lists the articles or the orders behind its count, one link apiece, on the Health check page.

---

## Structured data

A product sheet publishes a schema.org `Product` graph as JSON-LD - name, image, description, sku, brand,
category, and one `offers` node per purchasable item carrying its own title, description, picture, sku, gtin,
price, list price, currency, availability and condition. That is what a search engine shows as a rich result, and what any site reading the
shop from outside needs to know what is on sale and for how much.

The graph is built by `ProductSnippetBuilder` and rendered by a Twig function rather than by a template of the
bundle, so overriding `product/display.html.twig` in your app keeps the markup - just call it again, the items'
pictures being resolved by the function itself:

```twig
{% set jsonLd = product_json_ld(product, ogImage|default(null), url('product_display', {'slug': product.slug})) %}
{% if jsonLd %}
    <script type="application/ld+json">{{ jsonLd }}</script>
{% endif %}
```

This is the one place of the c975L ecosystem that emits an `offers` node: BookBundle's own `Book` graph
deliberately leaves it out, so a book sold through this shop never publishes its price twice.

Availability follows the very rules the "add to basket" button is disabled on: an item limited to `0` is
`OutOfStock`, one whose orders reached its limit is `SoldOut`, a product with a future release date is
`PreOrder`, everything else is `InStock`.

Three references make the graph readable by a merchant listing rather than by a search engine alone.
`Product.brand` names the maker, which Google Merchant Center declines a branded offer for not stating.
`ProductItem.sku` is the shop's own reference, and falls back on the item's slug when left empty - which is
what every offer of this graph carried before the column existed. `ProductItem.gtin` is the barcode number, 8
to 14 digits: an EAN-13 on a shelf product, an ISBN-13 on a book. It is what lets a comparison engine
recognize the same product sold by someone else, and a product made in-house simply carries none - claiming
one it does not have is worse than publishing nothing. All three are left out of the graph when unstated.

An item on offer publishes its previous price as a `priceSpecification` of type `ListPrice`, which is what
Google shows as a crossed-out price. It is guarded by the very rule the sheet prints its struck-through price
on, so the graph and the page can never advertise two different offers.

The condition is stated item by item in the back-office - new, used, refurbished or damaged - because the same
product can be sold in two of them at once. Leaving it unstated publishes nothing: Google reads `itemCondition`
for its merchant listings, but a graph claiming "new" over a second-hand item is worse than a graph saying
nothing.

Each offer also carries what the sheet already displays about delivery and returns: the shipping rate from
PaymentBundle's `shop-shipping`, on the items that are actually posted - a downloaded file and a rendered service
carry none - and a `merchantReturnLink` pointing at `url-terms-of-sales`. Neither a destination country nor a
return window is published: nothing in the ecosystem holds them, and a guessed one is a promise the shop never
made.

The shop's index and a category page publish an `ItemList` of the cards they print, through a second function
taking the products the page shows and, where it paginates, how many the pages before it already listed:

```twig
{% set productsJsonLd = shop_products_json_ld(products, (products.getCurrentPageNumber - 1) * perPage) %}
{% if productsJsonLd %}
    <script type="application/ld+json">{{ productsJsonLd }}</script>
{% endif %}
```

Each element carries a name and an url only, the full graph living on the sheet it points at, and the list holds
exactly what the page shows - a draft or a trashed product is not on the page and so is not in the list. The count
is that of the page and never that of the whole catalog, which a validator refuses, and a page printing no card
publishes nothing.

---

## Wish list

`shop-favorites`, on out of the box, puts UiBundle's heart on the product sheet — next to the rating widget and
before the description — and on every card of the shop listing, outside the card's own link, a button inside a
link being invalid html a click would answer by navigating instead. Nothing personal is printed either way: the
heart is painted from the visitor's own browser, so a card sitting in the block cache stays shareable.

The list itself is UiBundle's, on `/favorites`, and follows a signed-in customer from one device to the next.
What this bundle adds is `ShopFavoriteItemProvider`, which turns the `shop_product` rows the list holds back
into cards — UiBundle stores a name and an id and nothing else. A product taken offline simply stops showing on
the lists it was on, and comes back on them the day it is published again.

---

## Back-in-stock alerts

An item whose stock ran out shows a **Tell me when it is back** button beside its disabled add button, and the
visitor leaves an email address on a page of its own. That page is not the sheet: the sheet's html is handed to a
shared cache per fragment, where a form needs a session, a csrf token and a `Set-Cookie` — the three things that
must never travel with a cached page.

**Sold out is not withdrawn**, and the distinction decides everything. An item is offered for subscription only
when it is capped and the cap has been reached (`orderedQuantity >= limitedQuantity`); an item set to
`limitedQuantity` 0 was taken off sale and promises nothing, so it offers nothing. The rule itself is
`ProductStateServiceInterface::isItemSoldOut()`, read by the button, the badge and the alerts alike.

The subscription is held against the **item**, not the product: somebody waiting on the paperback is not told the
ebook is back. It carries the language it was taken in — there is no order here to read a locale from — and an
unsubscribe token, so the link the email carries never names the address itself. Subscribing twice puts the row
back on the waiting list rather than being refused.

`c975l:shop:stock-alerts:send`, scheduled hourly, walks the queue in batches: a restocked best-seller can carry
thousands of subscriptions, and sending them in one pass would hold the mailer for as long as it takes. Each run
sends at most `--limit` alerts (50 by default) and says how many are still waiting, so a queue that stops going
down is how a shop finds out its mailer is refusing. A send that failed leaves the row waiting and is tried again
next run. Nothing is sent on an item that is back but taken offline, on a product unpublished, trashed or not yet
on sale — an email the visitor cannot act on is worse than none.

The message itself is an **`EmailTemplate` named `back_in_stock`**, declared by `ShopEmailTemplateProvider` and
seeded by `c975l:ui:email-templates:ensure`, so its wording is rewritten in the back-office rather than in a Twig
file. Its default sentences are read from the `shop` catalogs, which is also what a translator edits for a
language this bundle does not ship. It travels from the `shop-email-*` addresses PaymentBundle declares, falling
back on the site-wide `email-*` when they are blank.

The public route is rate-limited: `c975LShopBundle` declares `shop_stock_alert` (10 an hour per caller) so a site
that never configured a limiter is not serving it unlimited, and the form carries the same honeypot every public
form of the ecosystem does.

---

## Customer ratings

`shop-rating`, on out of the box, puts UiBundle's rating widget on the product sheet, between the categories and
the description, and a compact one at the bottom of every card of the shop listing — the score and the way to set
it, without how many voted, which a card has no room for. The listing reads every tally in **one query** and hands
each card its own (`ui_ratings()`), and only the shop's index asks for the widget: a card rendered inside a block
kind goes into the block cache, whose html is shared by every visitor and would freeze the averages with it. The sheet only says *what* is rated — `ownerType="shop_product"` and the product's id — the
symbol and the scale being the site's own settings (`ui-rating-icon`, `ui-rating-scale`), shared with whatever
else it rates. A scale of 1 turns the whole thing into a plain "like".

Everything else is UiBundle's: one vote per account or per browser, no login required, no cookie banner, and the
vote sent to its own uncached route so the sheet stays cacheable. See its **Visitor ratings** section.

Removing a product for good drops its ratings (`ProductCrudController::deletePermanently()`) — never on the way
to the recycle bin, one that can still be restored having to find them where it left them.

---

## Gift cards

An item whose **gift card value** is filled in is sold as a gift card. Everything else about it is an ordinary
item: it has its own price, its own VAT, its own stock, and it is delivered the way its format says - a code
sent by email, or a card printed and posted. The value is what the card is worth once received, in the item's
own currency, and is deliberately not the price: a EUR 50 card sold at EUR 45 is a EUR 50 card.

`ProductItem::isGiftCard()` is true as soon as that value is set. `ProductBasketItemProvider` then adds
`Basket::CONTENT_FLAG_GIFT_CARD` to the flag saying how the item is delivered - both travel, a card being money
bought in advance *and* something to hand over - and, once the order is paid, asks PaymentBundle's
`GiftCardService` to issue **one card per unit bought**, each with a code of its own.

Everything after that is PaymentBundle's: the codes, their balance, how they are spent at checkout and what an
expired one does. This bundle only says which item is one, what it is worth, and what it looks like.

> Leave the VAT at 0 on such an item: a multi-purpose voucher is taxed when it is spent, not when it is sold.

### The visual, and the block that sells it

**One product per visual, its items being the amounts.** A shop selling three designs at 20, 30 and 50 EUR
writes three products with three items each, not nine products - the visual is what a customer picks, the amount
is picked under it. `Product::isGiftCard()` answers off its items rather than being stored twice.

Two fields say what the card is printed with, under the product's own **Gift card** fieldset:

| Field | What it does |
| --- | --- |
| `giftCardText` | The words on the recto, beside the logo and the amount - "Bon cadeau", "Joyeux anniversaire" |
| `giftCardScratch` | Whether the code hides under a panel to be scratched off on the card's own page |

The picture is the product's **first media**, and the logo is the site's own - nothing to upload twice, and no
verso to draw: PaymentBundle mirrors and fades the recto for it.

**The visual travels with the basket, not with the card's row.** `toBasketData()` copies it onto the basket
(`parent.giftCardText`, `parent.giftCardScratch`, `parent.image`) and `onBasketPaid()` hands it over as a
`c975L\PaymentBundle\Contract\GiftCardDesign` - a card is minted from the provider's own request, which knows
nothing of this catalogue, and a design withdrawn from sale must not blank a card somebody still holds.

The `shop_gift_cards` block lists those visuals with their amounts, each amount being an ordinary add button, so
a card is put in the basket the way anything else is.

---

## Test mode

`shop-test-mode` is the third configuration entry this bundle declares. Switched on - from the dashboard's tile
rather than by hand - every page of the shop carries a warning banner telling the visitor that nothing is really
sold and nothing will be shipped, which is what a catalog still being filled in owes whoever lands on it.

It speaks for the catalog alone: PaymentBundle announces its own `payment-test-mode` with its own banner in the
basket, where the charge actually happens, so a visitor is never warned twice over the same page. Its wording is
`label.test_mode`, which your site translates its own way in `translations/shop.<locale>.xlf`.

---

## What the site's dashboard gets

Installed alongside ConfigBundle, this bundle contributes on its own, with nothing to declare in your app:

| Contribution | What it does |
| --- | --- |
| `MenuProvider` | The Products, Categories and Shop page entries of the management sidebar |
| `LinkableRouteProvider` | The shop and each of its categories, selectable as a SiteBundle menu target |
| `UrlMetadataProvider` | `/shop`, the one url of this bundle no entity describes, listed on the "Url descriptions" screen |
| `ProductExportProvider` / `ProductImportProvider` | The whole catalogue in the "Export sync (everything)" archive, and read back by the "Import content" screen (see [export / import products](#export--import-products)) |
| `ProductCategoryExportProvider` / `ProductCategoryImportProvider` | The categories with the blocks composed on their page, carried the same way and importable in any order |
| `ShopBackupPathProvider` | `public/medias/shop` and `private/medias/shop` declared as irreplaceable - **the private one holds the files your customers paid for** |
| `ShopShortcutProvider` | The shop test mode tile, under Maintenance, toggling `shop-test-mode` |
| `ShopStatusProvider` | Whether the shop shows its test banner, the orders waiting to be shipped, how long the oldest has waited, the payments started but never confirmed, and the four counts of a catalog published but not finished - a sheet with no picture, a description too thin to rank, a picture with no `alt`, a category with no description - in the `extra` section of `/status/report` |
| `ShopMaintenanceTaskProvider` | This bundle's two scheduled commands, so your site does not list them itself |
| `WhatsNewProvider` | This bundle's entries on the "What's new" screen |
| `StylesheetProvider` | The bundle's stylesheet, served in the site's single concatenated request |

---

## AI agent skills

The package ships four skills of its own, under `skills/`, written for the coding agent of the site
installing this bundle rather than for someone modifying it. Point your agent at them:

```text
vendor/c975l/shop-bundle/skills/
```

| Skill | Subject |
| --- | --- |
| `c975l-shop-catalog` | products, categories, items, the public pages, what a card says of itself |
| `c975l-shop-blocks` | the nine block kinds, composing the shop's three pages, the render cache |
| `c975l-shop-checkout` | the plug into PaymentBundle, stock, digital downloads, test mode |
| `c975l-shop-seo` | the `Product` graph, sitemap and llms.txt, the health report, recommendations |

They hold what an agent gets wrong when left to its own habits — that a product carries no price, that
`limitedQuantity: 0` means withdrawn and not unlimited, that the listing's order parameter is `order`
because KnpPaginator reserves `sort`, that the money settings are PaymentBundle's and must not be
redeclared here, that stock moves on payment and never on adding to the basket, that a bought file is
copied per purchase rather than served from `private/` — alongside the routes, the entities, the config
slugs, the blocks, the Twig functions and the components, each named as it actually is in the sources.

Nothing is installed, nothing is copied into your project: the files sit in `vendor/` like any other
part of the package and follow it at each `composer update`. A user of Claude Code wanting one to load
by itself symlinks it into their own skills directory:

```bash
ln -s ../../vendor/c975l/shop-bundle/skills/c975l-shop-catalog .claude/skills/c975l-shop-catalog
```

`Tests\SkillsTest` keeps them honest: every path, route, config slug, command, class member, Twig
function, block kind and component they quote is checked against the sources, so renaming any of them
fails the suite instead of leaving an agent confidently wrong.

---

## Upgrading

When upgrading between major versions, refer to the [UPGRADE.md](UPGRADE.md) guide for migration instructions and breaking changes.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/ShopBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/ShopBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!
