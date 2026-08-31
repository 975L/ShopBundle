---
name: c975l-shop-seo
description: "Use this skill when working on how the shop is read from outside in a Symfony application built on the c975L ecosystem — the schema.org Product graph and its offers, the shop's sitemap and llms.txt section, the health report of the catalog, and the recommendations built from co-purchase affinities. Covers why this is the ecosystem's only offers node and why the affinities are recomputed rather than read live. Triggers on: ProductSnippetBuilder, product_json_ld, products_json_ld, shop_products_json_ld, buildItemList, ItemList, numberOfItems, ProductJsonLdExtension, offers, InStock, OutOfStock, SoldOut, PreOrder, itemCondition, shippingDetails, shippingDestination, shippingRate, ShippingRateResolverInterface, shop-shipping-country, weight, merchantReturnLink, ShopSitemapProvider, sitemap-shop.xml, llms.txt, SeoFilesWriter, ShopStatusProvider, productsWithoutImage, mediasWithoutAlt, ProductStructuredDataHealthCheckProvider, ProductJsonLdClient, product-json-ld, ProductAffinity, ProductRecommendationService, BasketRecommendationProviderInterface, getTemplate, c975l:shop:affinity:calculate, ogImage, summarySocialNetwork."
---

# c975L ShopBundle — structured data, sitemap, health, recommendations

> A shop is read by machines as much as by visitors: a search engine wants a price, a model wants a description, and the shop's owner wants to be told which sheets neither of them can use.

**Package:** `c975l/shop-bundle` · **Bundle:** `c975L\ShopBundle\` · **Twig namespace:** `@c975LShop` · **Translation domain:** `shop`

**Key source paths:**
`src/Service/ProductSnippetBuilder.php`, `src/Twig/ProductJsonLdExtension.php`, `src/Service/ShopBreadcrumbBuilder.php`, `src/Management/ShopSitemapProvider.php`, `src/Management/ShopStatusProvider.php`, `src/Management/ProductStructuredDataHealthCheckProvider.php`, `src/Service/ProductJsonLdClient.php`, `src/Management/UrlMetadataProvider.php`, `src/Service/ProductRecommendationService.php`, `src/Entity/ProductAffinity.php`, `src/Command/CalculateProductAffinityCommand.php`, `templates/product/display.html.twig`, `templates/category/display.html.twig`, `templates/shop/index.html.twig`

**Related skills:** `c975l-shop-catalog`, `c975l-shop-blocks`, `c975l-shop-checkout` in this same bundle, and `c975l-management`, `c975l-operations` in ConfigBundle beside it.

## The Product graph

A product sheet publishes a schema.org `Product` as JSON-LD, with **one `offers` node per purchasable
item** carrying its own title, description, picture, sku, gtin, price, list price, currency,
availability, condition, shipping details and return link. The product node itself carries a `brand`.
Purchasable means **not set aside**: both the builder and the extension read `Product::getVisibleItems()`,
an item hidden publishing no offer for something the sheet no longer sells.

```twig
{% set jsonLd = product_json_ld(product, ogImage|default(null), url('product_display', {'slug': product.slug})) %}
{% if jsonLd %}
    <script type="application/ld+json">{{ jsonLd }}</script>
{% endif %}
```

A Twig function and not a template, so an app overriding `product/display.html.twig` keeps its
structured data by calling the same function — the items' picture urls are resolved by the function
itself.

**This is the one place in the c975L ecosystem that emits an `offers` node.** BookBundle's own `Book`
graph deliberately leaves it out, so a book sold through this shop never publishes its price twice.
**Do not add an `offers` node anywhere else.**

Availability follows the very rules the add button is disabled on: `limitedQuantity: 0` is
`OutOfStock`, orders reaching the limit are `SoldOut`, a future `availableAt` is `PreOrder`, everything
else `InStock`. **Those rules live in one place per concern** — the graph here, the badge in
`ProductStateService` (see `c975l-shop-catalog`) — and a change to one is a change to both.

`shippingDetails` is priced by PaymentBundle's `ShippingRateResolverInterface` on **that item's own
weight**, for the one country `shop-shipping-country` names, which the node states as its
`shippingDestination`. Five cases publish **nothing at all** rather than a guess: a downloaded file, a
rendered service, an item nobody weighed, a shop naming no country, and a grid answering nothing for
that parcel. What tells a posted item from the rest is `ProductBasketItemProvider`'s rule, so a graph
and a delivery note never disagree. **Do not publish a tier of the grid as if it covered every parcel**,
and do not publish a zero rate, which reads as free shipping.

`sku` is the shop's own reference, and **falls back on the item's slug** when the column is left empty,
which is what every offer carried before the column existed. `gtin` is the barcode number — an EAN-13
on a shelf product, an ISBN-13 on a book — and is what lets a comparison engine recognize the same
product sold elsewhere; a product made in-house carries none, and claiming one it does not have is
worse than publishing nothing. `brand` sits on `Product`, not on the item, and Google Merchant Center
declines a branded offer naming none.

An item on offer also publishes a `priceSpecification` of type `ListPrice`, which is what Google shows
as a crossed-out price. Its guard is `ProductStateService::getItemPriceBefore()` — the very one the
struck-through price on the card is printed on — so the graph and the sheet can never advertise
different offers.

`itemCondition` is stated item by item, because the same product can be sold new and second-hand at
once. **Leaving it unstated publishes nothing**: a graph claiming "new" over a used item is worse than
a graph saying nothing. The same restraint applies throughout — an empty field is dropped, never
published blank, and no destination country or return window is published because nothing in the
ecosystem holds them.

## The listing's ItemList

The shop's index and a category page each publish an `ItemList` of the cards they print, through
`shop_products_json_ld()`:

```twig
{% set productsJsonLd = shop_products_json_ld(products, (products.getCurrentPageNumber - 1) * perPage) %}
```

It lists **the products the page shows**, in the order it shows them, which is the already-filtered set:
a hidden or a trashed product is not on the page and so is not in the list. Each element carries a name
and an url only — the summary form, the full graph living on the sheet the url points at.

`$offset` is what the pages before this one already listed, handed over by the template from the
paginator rather than guessed at here, so the second page numbers its cards from where the first
stopped. A category page passes none, being a single page. `numberOfItems` counts **the elements this
page carries**, never the whole catalogue: a count claiming more than the list below it is what a
validator refuses. A card with no name or no url is dropped rather than numbered, a list whose
positions skip one being malformed, and a page printing no card publishes nothing at all.

## The breadcrumb

A product sheet and a category page each publish a `BreadcrumbList` in a **second `<script>` tag**,
beside the product graph rather than merged into it:

```twig
{% set breadcrumbJsonLd = shop_breadcrumb_json_ld(product) %}
```

`ShopBreadcrumbBuilder` resolves the trail — shop, first category, page — and `shop_breadcrumb()`
hands the same levels to the `Shop:Breadcrumb` component printed above the page. **Both read the one
builder**, so the markup never claims a trail the visitor is not shown. The trail starts at the shop
and not at the site's home page: that route belongs to whichever bundle draws the site, and this one
runs without any of them. A trail of fewer than two levels publishes nothing.

The sheet also sets `ogImage`, `ogImageAlt` and `summarySocialNetwork` for the layout. The template
setting the share image is the only one that knows what it shows, so it says it there rather than
leaving the layout to read a media column this bundle's medias do not have.

## Sitemap and llms.txt

`ShopSitemapProvider` declares the shop, its products and its categories as `sitemap-shop.xml`, written
by ConfigBundle's `SitemapWriter`. Product and category urls also carry a `title` and a `description`,
which the sitemap ignores and which `SeoFilesWriter` turns into the **Shop** section of the site's
`llms.txt`.

The shop's own url deliberately carries neither: a heading pointing at the catalog's front door says
nothing a model can use, and an url with no title is simply left out.

**Nothing is declared before `site-url` is configured** — a sitemap only accepts absolute urls, so the
provider returns an empty array rather than relative ones.

Descriptions are passed as they are; the writer strips the markup, flattens and truncates. **Do not
pre-truncate them here.**

## The health report

`ShopStatusProvider` reports under the `shop` key: `productsWithoutImage`,
`productsWithThinDescription`, `mediasWithoutAlt`, `categoriesWithoutDescription`.

The four are the catalog gaps that keep a sheet from ranking or from being read by a screen reader.
**The order backlog is not here:** `ordersToShip`, `stalledPayments` and the payment test mode are read
off `Basket` and off the payment config, so `PaymentStatusProvider` carries them under the `payment` key
— a site running Payment without Shop reported none of them while they lived here.

`ProductStructuredDataHealthCheckProvider` (kind `product-json-ld`) is the other half: it fetches every
product sheet through `ProductJsonLdClient` and reports the ones serving no structured data, one that
does not parse, or one that parses without describing a `Product`. **The check belongs to whoever emits
the markup** — the builder being right proves nothing about a site whose own template stopped calling
`product_json_ld()`.

## Recommendations

`ProductAffinity` stores a co-purchase count and a score per product pair, recomputed by
`c975l:shop:affinity:calculate` — monthly, since it is a full pass over the orders.
`ProductRecommendationService` scores candidates on three criteria: same category (45 points), similar
price (20), historical co-purchases (35).

**A product's hand-picked `relatedProducts` come first and replace the calculation entirely** — the
affinities read a sales history, which is exactly what a catalogue just filled in does not have. Only
published, non-trashed picks are kept. Falling back on the calculation happens only when nobody picked
anything.

It serves both the sheet's "you might also like" (`getSimilarProducts()`) and PaymentBundle's basket
recommendations (`BasketRecommendationProviderInterface`). Only `product` items are read from a basket —
never a crowdfunding counterpart or a lottery ticket.

`getTemplate()` names `@c975LShop/components/Product/Recommendations.html.twig`, which the basket page
includes with the entries as a `recommendations` variable. **The markup belongs here, not to
PaymentBundle** — the entries are `Product` entities, which it knows nothing of.

**The affinities are a stored score, not a live query.** A block reading them shows what the last run
computed, and that command invalidates the block cache itself: its bulk `DELETE` fires no Doctrine
event.

## Do not

- **Do not emit an `offers` node from another bundle.**
- **Do not publish an empty or guessed field** — drop it instead.
- **Do not claim a condition** the back office left unstated.
- **Do not duplicate the availability rules** — they belong to the snippet builder and to
  `ProductStateService`, and must agree.
- **Do not render structured data from a template** — call `product_json_ld()` and `shop_breadcrumb_json_ld()`.
- **Do not publish a `gtin` a product does not have**, and do not invent a `sku` — the slug already stands in.
- **Do not write a breadcrumb by hand** — the printed trail and its markup both come from `ShopBreadcrumbBuilder`.
- **Do not build sitemap urls before `site-url` is set**, and do not truncate a description there.
- **Do not compute affinities live** on a page render.
- **Do not read non-`product` basket items** when scoring recommendations.
