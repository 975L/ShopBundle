---
name: c975l-shop-catalog
description: "Use this skill when working with the shop's catalog in a Symfony application built on the c975L ecosystem — products, categories, purchasable items, their pictures and downloadable files, the public listing and product sheet, ordering and searching, and what a card says of itself. Covers where the money settings actually live and how the shop is composed in the back-office rather than overridden. Triggers on: Product entity, ProductCategory, ProductItem, ProductMedia, ProductItemMedia, ProductItemFile, ProductStateService, shop_product_state, shop_item_format, ShopService, ProductService, ProductCategoryService, ProductRepository, findAllSorted, shop_index, product_display, category_display, limitedQuantity, orderedQuantity, itemCondition, availableAt, giftCardValue, giftCardText, giftCardScratch, isGiftCard, ProductDuplicator, ProductExportProvider, ProductImportProvider, ProductCategoryExportProvider, ProductCategoryImportProvider, export selection, import content, isPublished, isDeleted, getPublishedItems, product_preview, recycle bin, ProductSearchComponent, CategorySelectorComponent, ShopSettings, shop_settings, category blocks, shop-currency, shop-shipping, shop-shipping-free."
---

# c975L ShopBundle — catalog

> A product is a sheet; what is actually bought are its items. Everything a listing shows about a product — its price, its formats, whether it can be bought at all — is read from those items, never stored on the product.

**Package:** `c975l/shop-bundle` · **Bundle:** `c975L\ShopBundle\` · **Twig namespace:** `@c975LShop` · **Translation domain:** `shop`

**Key source paths:**
`src/Entity/Product.php`, `src/Entity/ProductCategory.php`, `src/Entity/ProductItem.php`, `src/Entity/Media.php`, `src/Repository/ProductRepository.php`, `src/Service/ProductStateService.php`, `src/Service/ShopService.php`, `src/Management/ProductDuplicator.php`, `src/Management/ProductExportProvider.php`, `src/Management/ProductImportProvider.php`, `src/Twig/ProductStateExtension.php`, `src/Twig/Components/`, `templates/shop/`, `templates/product/`, `templates/category/`, `templates/components/`, `sass/`

**Related skills:** `c975l-shop-blocks`, `c975l-shop-checkout`, `c975l-shop-seo` in this same bundle, and `c975l-blocks`, `c975l-media` in UiBundle beside it.

## The three entities

| Entity | Holds | Never holds |
| --- | --- | --- |
| `Product` | title, slug, description, `brand`, `availableAt`, position, `isPublished`, `isDeleted`, medias, categories, blocks, `relatedProducts`, `giftCardText`, `giftCardScratch` | a price |
| `ProductItem` | price and `priceBefore` (**cents**), currency, vat, `sku`, `gtin`, `limitedQuantity`, `orderedQuantity`, `service`, `itemCondition`, `isPublished`, `giftCardValue`, one media, one file | its own page, a recycle bin |
| `ProductCategory` | name, slug, description, products (`ManyToMany`) | a price |

`Product` implements `HasBlocksInterface` — its sheet is composed in the back office, see
`c975l-shop-blocks`. Prices are integers in cents throughout; divide by 100 at display and never store
a float.

## Draft, recycle bin, and what the public may read

`Product` carries two flags, and **every public read honours both**: `isPublished` (a product is a draft until an
admin publishes it) and `isDeleted` (the recycle bin — `ProductCrudController`'s delete action only sets this).

| Read through | Answers |
| --- | --- |
| `ProductRepository::findAllSorted()`, `search()`, `findByCategorySlug()`, `findRandomProducts()`, `findAvailableProductsExcluding()`, `findByCategoriesExcluding()` | published, not trashed, past `availableAt` |
| `ProductRepository::findOnePublishedBySlug()` | published, not trashed — what blocks and components naming a product read |
| `ProductRepository::findOneBySlug()` | any state — the sheet itself, which then answers 410/404 |
| `ProductRepository::findNotDeleted()` | drafts included, trash excluded — the back-office pickers (`ShopBlockChoices`) |

`ProductController::display()` throws `GoneHttpException` for a trashed product and a 404 for a draft;
`product_preview` (`/shop/products/{slug}/preview`, admin only) renders the same sheet with the block cache disabled
and a banner. **A new lookup added anywhere must pick from that table rather than write its own `where`** — a draft
leaking into a listing, a block, a basket (`ProductBasketItemProvider::validateAddition()` refuses one) or the
sitemap is the failure mode this split exists to prevent.

Urls are kept in order through ConfigBundle's `Redirect`: renaming a product writes a 301 from `/shop/products/<old>`,
deleting it for good writes a `gone` row answering 410 there permanently.

`ProductItem` carries **one** flag of its own, `isPublished`: an item taken off sale keeps its file, its picture and
its stock but leaves everything the public reads. Read it through **`Product::getPublishedItems()`** — the sheet, the
`shop_product_items` block, `ProductStateService`, `ProductSnippetBuilder`, `ProductJsonLdExtension` and
`ProductRecommendationService` all do, and `ProductBasketItemProvider::validateAddition()` refuses one. `getItems()`
still holds them all, and is what the back-office form, `ProductExportProvider` and `ProductDuplicator` read.
There is deliberately **no `isDeleted` on an item**: the recycle bin exists to keep a url answering 410 while it can
still be restored, and an item has no url — so an item nobody wants back is deleted outright.

## Duplicating a product

`ProductDuplicator` (`src/Management/`) copies a whole product — its pictures, its items with their own
picture and downloadable file, its categories and the blocks of its sheet — behind the `duplicate` action
of `ProductCrudController`, available from the products index and from a product's own edit screen.

Two rules it exists to enforce, to be honoured by anything else copying a media: **the file is copied on
the disk under a name of its own**, never shared with the original, which deleting either product would
otherwise take from the other; and the copy is never handed back to Vich, whose storage *moves* the file
it is given and would re-run the resizing and the thumbnail generation. A block naming the product it
sits on (`productSlug`) is repointed at the copy, one naming another product is left alone, the
items' `orderedQuantity` is reset — nothing has been sold of a copy — and **the copy is a draft**, whatever the
original was.

**`limitedQuantity` has three meanings, and every read must honour all three:** `null` is an unlimited
stock, `0` an item withdrawn from sale, anything above `orderedQuantity` still buyable. Treating `0` as
"unlimited" puts a withdrawn item back on sale.

## What a card says of itself

`ProductStateService` resolves it once, from the **published** items, and the listing, the sheet, the
recommendations and the `shop_product` block all read the same answer:

```twig
{% set state = shop_product_state(product) %}
{{ state.badge|trans({'%discount%': state.discount}, 'shop') }}   {# label.coming_soon | label.sold_out | label.discount | label.limited_quantity | label.free | label.new | null #}
{{ (state.price / 100)|format_currency(state.currency) }}
{{ shop_item_format(productItem)|trans({}, 'shop') }}   {# label.physical | label.digital | label.service #}
```

`state` carries `badge`, `soldOut`, `availableAt`, `price` (the lowest of the items), `priceBefore`,
`discount`, `currency`, `singlePrice` and `formats`. The badge is the single most useful thing to say,
in that order of precedence: coming soon, sold out, discount, limited quantity, free, new (created
within 30 days). `label.discount` is the one badge carrying a figure, hence the `%discount%` parameter
passed to every key.

`priceBefore` is the struck-through price, and it is read from **the very item the card prices itself
on** — the cheapest one — so the two figures always name the same offer. `getItemPriceBefore()` and
`getItemDiscount()` (`shop_item_price_before()` / `shop_item_discount()` in Twig) do the same for a
single item on the sheet. Both **ignore a `priceBefore` that is not above the price**, and a cut
rounding down below 1 % publishes no discount at all: that guard is what keeps a stale value out of
the cards and out of the `ListPrice` the graph publishes. In the EU the figure must be the lowest
price charged over the last 30 days (Omnibus), which the form's help text says and no column
enforces.

**Compute none of this in a template.** A card recomputing "is it sold out" inline drifts from the
button that is or is not disabled, and from the `availability` the structured data publishes.

## The public pages

| Route | URL |
| --- | --- |
| `shop_index` | `/shop` — `?order=newest\|price_asc\|price_desc`, `?price=`, `?format=`, `?stock=`, `?p=` for the page |
| `product_display` | `/shop/products/{slug}` |
| `category_display` | `/shop/category/{slug}` |
| `shop_download` | `/shop/download/{token}` — see `c975l-shop-checkout` |
| `shop_terms_of_sales` | `/shop/terms-of-sales`, unless SiteBundle serves it |

**The order parameter is `order`, not `sort`** — that is the name the listing has been shared and
indexed under since it had page links. An order the listing does not offer falls back on the shop's
positions rather than erroring.

The listing grows on scroll rather than turning pages: UiBundle's `infiniteScroll` controller fetches
the `?p=` page the "Voir plus de produits" link points to and appends its cards. That link and the three
`data-infiniteScroll-target` attributes (`list` on the cards, `next` on the link, `count` in the footer)
are one contract with the controller, read in the fetched page as well as in the current one - renaming
either end leaves the listing stopped at twelve products. `?p=` stays a real page all the same: it is
what a crawler follows and what a visitor without javascript gets.

Price ordering happens in `ShopService`, not in DQL: a product's price is the lowest of its items,
which no `ORDER BY` can read without collapsing the rows the joined medias and items spread it over.
Products carrying no item close the list whichever way it is ordered.

`ProductRepository::findAllSorted()` joins medias, categories and items — the cards read all three, and
dropping a join puts an N+1 back on the listing.

The three filters read from the items are applied the same way, in `ShopService::filter()`:
`?price=min-max` in **cents** (upper bound exclusive, left empty on the open-ended last band),
`?format=physical|digital|service`, `?stock=available`. The price bands are cut from the catalogue's
own highest starting price (`ProductRepository::findMaxLowestItemPrice()`) — the very figure
`matchesPrice()` compares them against — never written out, so they suit the shop they are offered on. A `price` range is parsed rather than checked against the bands currently
offered, so a shared url survives the catalogue's prices moving. **They are on `/shop` only** — a
category page lists what its category holds, with no pagination of its own to filter.

## Composing the chrome

Nothing around the listing is written in a template any more: what the shop says of itself — delivery
terms, a welcome message, arguments, a footer band — is composed in the back-office with UiBundle's
kinds, in the three places this bundle owns blocks:

- **the shop's index** — the single `ShopSettings` row, edited on the dashboard's *Shop page* screen,
  rendered above the listing by `shop/index.html.twig`.
- **a category page** — `ProductCategory` blocks, edited in the category's own CRUD, rendered under its
  description.
- **a product sheet** — `Product` blocks, edited in the product's CRUD (see *Composing a product sheet*
  in the README).

`ShopBlockOwnerResolver` names the three owner types (`product`, `product_category`, `shop`), so a block
can be dragged from one to another, and `ShopBlockEditUrlProvider` points the front-end hover button at
whichever screen composes it.

Everything else is styled through CSS variables. `sass/_variables.scss` mixes its tokens out of
`--primary`, `--secondary`, `--text` and `--background` so a site retuning its theme moves the shop with
it; the two stock colours are the deliberate exception, a stock level being a status and not a brand.
Tokens are declared on `:root, [data-theme]`, per UiBundle's own contract.

The public templates are mobile first: a single column is the default and the grids widen through
`auto-fill` / `minmax()`. The items of a sheet are an **exclusive accordion built on
`<details name="product-items">`** — HTML's own, so this bundle ships no JavaScript of its own.

## Moving a catalogue between environments

`ProductExportProvider` / `ProductImportProvider` (and the `ProductCategory` pair beside them) carry a
product whole — pictures, items with the file they are bought for, sheet blocks, categories, related
products, published and trashed flags — as a zip, through ConfigBundle's **Export sync (everything)**
shortcut, the product index's **Export selection** batch action, and the **Import content** screen.
Everything is matched by slug, never by id: a product by its own, an item by its slug within its product.

Files are laid back under the names they were served under, so a synced catalogue keeps its image urls.
An import **never deletes what it cannot put back**: a product or an item the archive does not name is
left where it is, its file being what a customer has paid for. Affinities are recomputed rather than
carried, and the baskets' download links belong to the payments.

## Search and category selector

`ProductSearchComponent` and `CategorySelectorComponent` are Live Components. A Live Component needs a
**single root element carrying `{{ attributes }}`**, written whatever the shop holds — a selector
rendering nothing when there is no category yet breaks the component, so the root stays and its
contents are what disappears.

## Money settings are PaymentBundle's

`shop-currency`, `shop-shipping`, `shop-shipping-free`, `shop-name` and `url-terms-of-sales` are
declared and owned by **PaymentBundle**, read here through ConfigBundle's `config()`. This bundle
declares exactly one key of its own, `shop-test-mode`.

**Do not redeclare a `shop-*` money key in this bundle** — two declarations of one slug is a shop
charging one price and displaying another.

## Do not

- **Do not store a price on `Product`** — it is the lowest of its items, resolved at read time.
- **Do not store prices as floats**, and do not store a currency per product.
- **Do not treat `limitedQuantity: 0` as unlimited.**
- **Do not compute a badge, a price or a format in a template** — call `shop_product_state()`.
- **Do not read `product.items` in anything the public sees** — `getPublishedItems()` is the public read.
- **Do not add an `isDeleted` to `ProductItem`** — it has no url to protect; unpublish it or delete it.
- **Do not rename `?order=`** to `?sort=`; shared and indexed urls carry the name it has.
- **Do not drop the joins from `findAllSorted()`.**
- **Do not declare a `shop-*` money key here** — they are PaymentBundle's.
- **Do not add JavaScript for the items accordion** — `<details name>` already does it.
- **Do not hardcode a colour in `sass/`** — every one goes through a token.
- **Do not remove the root element of a Live Component** when its collection is empty.
- **Do not move a catalogue with the SQL/CSV/JSON dumps** — they carry one table at a time; the zip export carries a product whole.
