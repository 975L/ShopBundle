---
name: c975l-shop-blocks
description: "Use this skill when putting the shop's catalog on a page composed in the back office of a Symfony application built on the c975L ecosystem — the nine shop block kinds, composing the shop's index, its category pages and a product sheet out of blocks, the three kinds that read the product of the sheet they sit on, the render cache and its catalog tags, and the block showcase. Covers why these kinds store no product and why one of them declines its cache entry. Triggers on: shop_products, shop_gift_cards, shop_categories, shop_product, shop_product_button, shop_search, shop_recommendations, shop_product_items, shop_product_slider, ShopBlockExtension, shop_block_products, shop_block_gift_cards, shop_block_categories, shop_block_product, shop_block_recommendations, shop_block_sheet_kinds, ShopBlockChoices, ShopBlockCacheTagProvider, ShopCacheInvalidationListener, ShopBlockCacheInvalidator, ShopBlockOwnerResolver, ShopShowcaseProvider, shop_product context, shop_product_block, shop_product_category_block, shop_settings_block, ShopSettings."
---

# c975L ShopBundle — blocks

> The catalog reaches any page of the site as blocks, and a product sheet is composed out of them. A block stores what to show, never what it shows.

**Package:** `c975l/shop-bundle` · **Bundle:** `c975L\ShopBundle\` · **Twig namespace:** `@c975LShop` · **Translation domain:** `shop`

**Key source paths:**
`config/services.yaml`, `src/Form/Block/`, `src/Twig/Extension/ShopBlockExtension.php`, `src/Service/ShopBlockChoices.php`, `src/Service/ShopBlockCacheTagProvider.php`, `src/Service/ShopBlockCacheInvalidator.php`, `src/Listener/ShopCacheInvalidationListener.php`, `src/Management/ShopBlockOwnerResolver.php`, `src/Management/ShopBlockEditUrlProvider.php`, `src/Service/ShopShowcaseProvider.php`, `templates/blocks/`, `templates/product/display.html.twig`, `templates/category/display.html.twig`, `templates/shop/index.html.twig`

**Related skills:** `c975l-shop-catalog`, `c975l-shop-checkout`, `c975l-shop-seo` in this same bundle, and `c975l-blocks`, `c975l-media` in UiBundle beside it.

## The nine kinds

All registered with UiBundle's `BlockRegistry` through the `ui.block` tag, all in the **Shop** category.

| Kind | Shows | Cacheable |
| --- | --- | --- |
| `shop_products` | a grid of products, by category, capped, optionally at random | yes, except at random |
| `shop_categories` | the links to the shop's categories | yes |
| `shop_product` | one product, as a card linking to its sheet | yes |
| `shop_product_button` | a button whose label and link follow the product | yes |
| `shop_search` | the live search, under a heading of its own when given one | **no** |
| `shop_recommendations` | the products bought with this one | yes |
| `shop_product_items` | a product's buyable items, as an accordion of rows | yes |
| `shop_product_slider` | a product's medias, in UiBundle's slider | yes |
| `shop_gift_cards` | the cards the shop sells, one visual each, the amounts under it | yes |

Every template under `templates/blocks/` is an **adapter**: it resolves what to show through a
`shop_block_*()` Twig function and hands it to the same component the bundle's own pages use. So
restyling a component moves the pages *and* the blocks at once, and there is nothing to keep in step
between the two.

**A block stores a slug and a maximum, never the products themselves.** `ShopBlockExtension` resolves
them live at render time, which is what keeps a block from going stale against the catalog: a product
renamed or deleted leaves the blocks pointing at it rendering nothing rather than half a card.

## The three kinds of a product sheet

`shop_recommendations`, `shop_product_items` and `shop_product_slider` accept **no product at all**:
left empty they show the product of the sheet they sit on, read from the current route. The last two
declare the `shop_product` context, so they are offered on a sheet and in a container's slot, and
nowhere else.

Placed on a sheet, each **takes over the hardcoded section it replaces**:

```twig
{% set sheetKinds = shop_block_sheet_kinds(product.blocks) %}
{% if 'shop_product_items' not in sheetKinds %}
    {# the bundle's own items section, rendered only when no block took it over #}
{% endif %}
```

`shop_block_sheet_kinds()` walks a container's slots too. The sheet keeps working untouched and yields
section by section as the editor places the blocks. **Never render both** — a sheet showing its items
twice is the failure this guard exists to prevent.

## The three pages that own blocks

`Product`, `ProductCategory` and `ShopSettings` all implement `HasBlocksInterface`, their collections
living in the `shop_product_block`, `shop_product_category_block` and `shop_settings_block` join tables -
so an existing installation needs `doctrine:migrations:diff` then `migrate`. `ShopSettings` is a single
row holding the blocks of `/shop`, created the first time its dashboard screen is opened.

`ShopBlockOwnerResolver` names the three owner types (`product`, `product_category`, `shop`) for
UiBundle's move screen, which is what lets a block be dragged from any of them to another, and
`ShopBlockEditUrlProvider` points the front-end hover button at whichever screen composes it.

Only the product sheet's collection declares the `shop_product` context: the two sheet-only kinds have
no product to read on a category page or on the index, and are kept out of those pickers.

## The render cache

The kinds resolve their content live, which no `Block` or `Media` event ever signals a change of — so
UiBundle's own invalidation listener cannot close the gap. Each entry carries a **catalog tag**
(`ShopBlockCacheTagProvider`), and `ShopCacheInvalidationListener` drops it whenever a `Product`,
`ProductItem`, `ProductMedia`, `ProductCategory` or `ProductAffinity` changes.
`c975l:shop:affinity:calculate` invalidates them explicitly: its bulk `DELETE` fires no Doctrine event.

Two deliberate exceptions:

- **`shop_search` is `cacheable: false`** — it renders a Live Component, whose markup carries the props
  checksum and the CSRF token of the current session.
- **A `shop_products` block drawing at random returns `null` from its tag resolver**, i.e. renders
  live: a cached entry would freeze the draw until the catalog itself changed.

Adding a kind that reads the catalog means adding it to `ShopBlockCacheTagProvider::PRODUCT_KINDS`, or
declaring it `cacheable: false`. Forgetting both serves a stale catalog until the next `cache:clear`.

## The showcase

`ShopShowcaseProvider` renders the nine kinds for a block showcase page. None of them fits
`BlockFixtureProviderInterface` — their templates query the catalog live — so each is rendered against
the same components with stand-in data. **It ships no media of its own**: the images come from whatever
the hosting site declares through `PlaceholderMediaProviderInterface`, and a site declaring none simply
gets no shop showcase.

The stand-ins are built from `ShopSampleCatalog`, the same made-up catalogue a demo site is seeded with
(see the `c975l-shop-catalog` skill) — enriching it there shows up here, and it is not written twice.

## Do not

- **Do not store a product's data in a block** — store its slug and resolve it at render time.
- **Do not render a sheet's section without checking `shop_block_sheet_kinds()`** first.
- **Do not cache a kind that renders a Live Component** or draws at random.
- **Do not add a catalog-reading kind** without a tag resolver or `cacheable: false`.
- **Do not duplicate a component in a block template** — the block is an adapter onto the same one.
- **Do not give a showcase entry a media file of its own.**
- **Do not put a `shop_product`-context kind** in a collection that has no product to read.
