---
name: c975l-shop-checkout
description: "Use this skill when working on buying, paying for or delivering a shop item in a Symfony application built on the c975L ecosystem — plugging products into PaymentBundle's basket, the add-to-basket button and its stock rules, stock decremented on payment, digital files handed out through expiring one-time links, gift cards issued on payment, and the shop's test mode. Covers who owns the basket (PaymentBundle, not this bundle) and why a bought file is copied per purchase, and why that copy lives under private/. Triggers on: ProductBasketItemProvider, BasketItemProviderInterface, WeighableBasketItemProviderInterface, CatalogueBasketItemProviderInterface, getCatalogueUrl, Basket:ContinueShoppingButton, getWeight, weight, toBasketData, getContentFlags, onBasketPaid, onBasketValidated, CONTENT_FLAG_DIGITAL, CONTENT_FLAG_GIFT_CARD, giftCardValue, isGiftCard, giftCardText, giftCardScratch, GiftCardService, GiftCardDesign, shop_gift_cards, gift card, basket controller, basket#addItem, ProductItem:AddButton, Shop:NavbarBasket, Basket:Navbar, ProductItemDownload, ProductItemDownloadService, ProductItemDownloadMessage, ProductBasketDownloadProvider, BasketDownloadProviderInterface, getFileItems, liveByItem, recordDownloaded, shop_download, VichPrivateFileInterface, PrivateFileResponseFactory, c975l:shop:downloads:delete, shop-test-mode, payment-test-mode, Shop:TestMode, ShopMaintenanceTaskProvider, ProductItemStockAlert, ProductItemStockAlertService, ShopEmailTemplateProvider, back_in_stock, shop_stock_alert_new, shop_stock_alert_unsubscribe, c975l:shop:stock-alerts:send, isItemSoldOut, isItemAvailable, shop_stock_alert, ShopIntegrityHealthCheckProvider, ShopIntegrityHealthCheckAdviceProvider, shop-integrity, undelivered-downloads, missing-files, oversold-items, free-items, findSellable, findDeliveredBasketIds."
---

# c975L ShopBundle — buying, paying, delivering

> This bundle sells; it does not own a basket. Products are plugged into PaymentBundle's engine through one provider, and everything after the payment — stock, download links, emails — hangs off one callback.

**Package:** `c975l/shop-bundle` · **Bundle:** `c975L\ShopBundle\` · **Twig namespace:** `@c975LShop` · **Translation domain:** `shop`

**Key source paths:**
`src/Service/ProductBasketItemProvider.php`, `src/Service/ProductItemDownloadService.php`, `src/Service/ProductBasketDownloadProvider.php`, `src/MessageHandler/ProductItemDownloadMessageHandler.php`, `src/Message/ProductItemDownloadMessage.php`, `src/Entity/ProductItemDownload.php`, `src/Entity/ProductItemFile.php`, `src/Controller/ProductItemDownloadController.php`, `src/Repository/ProductItemDownloadRepository.php`, `src/Command/ProductItemDownloadDelete.php`, `src/Scheduler/ShopMaintenanceTaskProvider.php`, `src/Management/ShopIntegrityHealthCheckProvider.php`, `src/Management/ShopIntegrityHealthCheckAdviceProvider.php`, `templates/components/ProductItem/`, `templates/components/Shop/`

**Related skills:** `c975l-shop-catalog`, `c975l-shop-blocks`, `c975l-shop-seo` in this same bundle, `c975l-payment-checkout`, `c975l-payment-items` in `c975l/payment-bundle`, and `c975l-config`, `c975l-operations` in ConfigBundle beside it.

## The one plug into PaymentBundle

`ProductBasketItemProvider` implements `BasketItemProviderInterface` and
`WeighableBasketItemProviderInterface`, and declares the kind `product`.
It is the **entire** contract between the shop and the checkout:

| Method | Does |
| --- | --- |
| `findItem($id)` | resolves a `ProductItem` |
| `validateAddition($item, $quantity)` | returns a message, or `null` when the addition is allowed |
| `validateCheckout($basket, $items)` | returns a message, or `null` when the whole basket can still be ordered |
| `toBasketData($item, $quantity)` | flattens the item and its product into what the basket stores |
| `getContentFlags($itemData)` | digital / service / physical, plus the gift card flag, which drives shipping |
| `onBasketPaid($basket, $items)` | raises `orderedQuantity`, dispatches the download message, issues the gift cards |
| `getWeight($itemData)` | what the line weighs in grams, the item's own weight times its quantity - `null` for what the shop never weighed |

**`validateAddition()` must let a removal through**: a negative quantity never needs stock, and
blocking it would trap an exhausted item in the basket. The stock rules there are the same three
readings of `limitedQuantity` as everywhere else — see `c975l-shop-catalog`.

It also refuses what has left the sheet while keeping its id: a hidden or trashed product, and an item
whose own `hidden` was ticked. An open page and a basket filled before that still carry the id.

**`validateCheckout()` is the door, and it is not `validateAddition()` again.** PaymentBundle asks it at
the very top of `BasketService::validate()`, before the gateway is looked up and before anything is
numbered, charged or persisted - the free path included. It receives the basket's entries with their
**whole quantity**, which is precisely what the other one cannot see: `validateAddition()` is asked one
click at a time and knows nothing of what the basket already holds, so five clicks on an item with one
left pass it five times. It also re-reads each item from the database rather than trusting what the
basket stored, because a basket sits for days and its product can be hidden, trashed or deleted in
between. A non-null answer raises `BasketNotOrderableException`, shown as a flash over an untouched
basket.

**Stock is decremented on payment, never on adding to the basket.** `onBasketPaid()` is the only place
`orderedQuantity` moves; a basket abandoned before paying must not have consumed anything - which is
exactly why `validateCheckout()` exists: nothing is reserved, so what is left has to be checked again at
the door.

**`toBasketData()` flattens rather than references.** It drops `product`, `creation`, `position`,
`modification` and `user` and copies the media and file names, so a basket keeps saying what was bought
at the price it was bought, whatever happens to the catalog afterwards.

## Gift cards

An item carrying a `giftCardValue` is a gift card, `ProductItem::isGiftCard()` saying so. The value is in
cents, in the item's own currency, and is **what the card is worth**, not what it was sold for: a card sold at
a discount is still worth its face value.

`getContentFlags()` adds `Basket::CONTENT_FLAG_GIFT_CARD` **on top of** the delivery flag rather than instead
of it - a code sent by email is digital, a card printed and posted is physical, and both are money bought in
advance. `onBasketPaid()` then calls PaymentBundle's `GiftCardService::issue()` **once per unit bought**, so
three cards in one basket entry are three codes.

**The visual is the product's, the amounts are its items'.** One product per design, its items being the sums it
is bought for: `giftCardText` and `giftCardScratch` sit on `Product`, `giftCardValue` on `ProductItem`, and
`Product::isGiftCard()` answers off the items rather than being stored twice. The picture is the product's first
media; there is no verso to upload, PaymentBundle mirroring the recto for it.

**Hand the visual over from the basket, never from the catalog.** `toBasketData()` copies it into `parent`
(`image`, `giftCardText`, `giftCardScratch`) and `onBasketPaid()` builds a
`c975L\PaymentBundle\Contract\GiftCardDesign` out of that copy - a card is minted from the payment provider's
own request, and a design withdrawn from sale must not blank a card somebody still holds.

The `shop_gift_cards` block lists those visuals, each amount rendered as an ordinary `ProductItem:AddButton`.

Nothing else about the cards lives here: the codes, their balance, how they are spent, when they expire and the
page whoever a card was bought for opens are PaymentBundle's. This bundle only says which item is one, what it is
worth and what it looks like.

## The add-to-basket button

`ProductItem:AddButton` renders the button PaymentBundle's `basket` Stimulus controller listens to
(`data-action="click->basket#addItem"`), carrying `data-item-id`, `data-quantity`, `data-limited` and
`data-ordered`. That controller also re-disables buttons from the live basket, so the `data-*` set is
part of the contract — **dropping one silently disables the stock check on the client.**

`Basket:Navbar` (PaymentBundle) is the bar shown as soon as the basket holds something, and it carries
PaymentBundle's own `Basket:ViewButton`. It is **placed once, in the site's layout**: this bundle's pages no
longer emit it, and `Shop:NavbarBasket` survives only as a one-line wrapper for the sites that overrode it.
That button already holds the `data-basket-target="total"` and `"quantity"` elements the controller fills.
**Do not add a second element carrying either target** — Stimulus fills only the first, leaving the other
permanently stale, which is exactly what a page emitting its own bar beside the layout's produces.

`ProductBasketItemProvider` also implements `CatalogueBasketItemProviderInterface`, whose
`getCatalogueUrl()` returns `shop_index` with a `#products` fragment: the basket's
`Basket:ContinueShoppingButton` is sent back to the articles rather than to the top of the shop page.
**PaymentBundle names no route of this bundle** — it draws no button at all when the provider answers
null. `Shop:ViewButton` is left for a page dropping it in, but the basket no longer calls it.

This bundle ships **no JavaScript**; everything on the client comes from PaymentBundle's `basket`
controller.

## Back-in-stock alerts

An item whose stock ran out carries a second button, linking to `shop_stock_alert_new` — a page of its
own, never a form on the sheet, whose html goes into the block cache where a csrf token and a session
must never travel.

**Sold out is not withdrawn, and this is the whole rule.** `ProductStateServiceInterface::isItemSoldOut()`
answers true only when the item is capped and the cap is reached; `limitedQuantity` at 0 means taken off
sale, nothing is expected back, and nothing is offered. `isItemAvailable()` states the buyability rule the
badge, the button and the alerts all read — **do not write it a fourth time.**

`ProductItemStockAlert` holds the item, the address, the locale it was taken in (there is no order to read
one from), an unsubscribe token and `notifiedAt`. Unique on `(product_item_id, email)`, so subscribing
again calls `renew()` on the row that exists rather than creating a second.

`c975l:shop:stock-alerts:send` walks the queue in bounded batches (`--limit`, 50 by default). A send the
mailer refused leaves `notifiedAt` null and is retried next run. Availability is decided in PHP by
`ProductStateService`, **not in the DQL** of `findPending()` — one rule, one place.

The message is the `back_in_stock` `EmailTemplate` declared by `ShopEmailTemplateProvider`, this bundle's
only one, seeded by `c975l:ui:email-templates:ensure`. Its sentences are read from the `shop` catalogs,
its locales are listed in the provider, and it goes out with `wrapLayout: false` — `renderNamed()` has
already wrapped it.

## Digital files

A bought file is never served from where it was uploaded. `ProductItemFile` implements
`VichPrivateFileInterface` and lives under `private/`; on payment:

1. `onBasketPaid()` dispatches `ProductItemDownloadMessage` for the basket.
2. The handler asks `ProductItemDownloadService::prepareFileForDownload()` per item, which **copies**
   the private file into **`private/downloads/`** under a name whose trailing hash gives way to a fresh
   16-character token, records a `ProductItemDownload` **expiring in `VALIDITY_DAYS` days**, and returns
   the token.
3. The handler emails the absolute `shop_download` URLs, generated here rather than in PaymentBundle's
   template: the route is this bundle's own, and both readers hand over a ready `url`.
4. `ProductItemDownloadController` resolves the token through `resolveFilePath()` and hands the copy over
   with UiBundle's `PrivateFileResponseFactory`.
5. `c975l:shop:downloads:delete` deletes the copies whose link is spent, and the rows kept past
   `RETENTION_DAYS` days to explain themselves.

**The copy is the point:** the link addresses a per-purchase file, so replacing or removing the original
never breaks a link already sold, revoking one buyer's copy touches nobody else, and two buyers never
share a URL.

**Where the copy lives is the point too:** `private/downloads/` is not served by the web server, so
`/shop/download/{token}` is the *only* way to it. The delay is enforced where the token is read rather
than by the nightly command having got there first, and `recordDownloaded()` cannot be walked around by
addressing the copy directly. A copy under `public/` would carry a second, unguarded URL to the same bytes.

**A source file gone from `private/` returns `null` rather than throwing** — the caller skips that item
instead of emailing a link to a copy that was never made.

`ProductBasketDownloadProvider` (PaymentBundle's `BasketDownloadProviderInterface`, autoconfigured, no
tag) is the second way to those files: the customer area asks it for the downloads of one paid order,
and it hands out **only the copies the delivery already made**, with their expiry date, never a fresh
one. **The basket is already checked as paid and as belonging to whoever asks**, so the provider never
re-checks ownership. Both readers walk the basket through `getFileItems()` and look the live copies up
through `ProductItemDownloadServiceInterface::liveByItem()` — keyed on
`ProductItemDownload::$productItemId` — so the email and the customer area can never disagree on what
was bought, and a retried email reuses the copies already made rather than minting a second set.

**`ProductItemDownloadMessageHandler` is the only place a copy is ever made.** Minting in the provider
too would keep a bought file reachable for as long as the shop kept selling it, while its email says
the link expires in `VALIDITY_DAYS` and the nightly purge takes the copy away — one promise, one
lifetime, one process. A buyer whose links ran out is offered nothing rather than a new set.

**An expired or missing copy renders `product/item_downloaded.html.twig`**, which words itself on whether
the link was ever used and only offers the contact button where ContactFormBundle is installed.

## Test mode

`shop-test-mode` is this bundle's only config key, toggled from the dashboard, and `Shop:TestMode`
reads **it alone**. PaymentBundle announces its own `payment-test-mode` with its own banner in the
basket, where the charge actually happens, so the two never warn twice over the same page. Never infer
test mode from the word "test" in a Stripe key.

## Scheduled work

`ShopMaintenanceTaskProvider` schedules this bundle's commands through the Symfony Scheduler, so a site
installing the shop gets them without a system crontab entry, and a site removing it stops running them:

| Command | Cadence |
| --- | --- |
| `c975l:shop:downloads:delete` | nightly |
| `c975l:shop:affinity:calculate` | monthly — a full pass over the orders |
| `c975l:shop:stock-alerts:send` | hourly — in batches, so a restock does not hold the mailer |

**Do not ask a consuming app to add these to its own schedule.**

## What is checked weekly

`ShopIntegrityHealthCheckProvider` (kind `shop-integrity`) runs four checks, one dashboard row each — the
catalogue-side counterpart of PaymentBundle's `basket-integrity`, which reads the orders themselves.

| Row | Reads |
|---|---|
| `#undelivered-downloads` | a paid order holding a file with no `ProductItemDownload` ever written for it — read no further back than `VALIDITY_DAYS`, the purge taking older copies away, and an hour's grace for the message handler |
| `#missing-files` | a sellable `ProductItemFile` whose file is not under `private/` — the sheet still sells it and the delivery skips the item rather than failing |
| `#oversold-items` | `orderedQuantity` past `limitedQuantity` |
| `#free-items` | a sellable item priced at zero — a warning, and reported only where it is the exception: a catalogue giving away half or more of what it lists has the row skipped |

Each check is guarded on its own: `HealthCheckRunner` drops **every** row of a provider that throws, and no rows
at all reads as "nothing to report".

`ShopIntegrityHealthCheckAdviceProvider` turns each count into the articles or the orders behind it, one link
apiece — a product to its edit screen, an order to its own read-only detail.

## Do not

- **Do not implement a basket, an order or a payment here** — they are PaymentBundle's.
- **Do not decrement stock when adding to the basket.**
- **Do not block a removal** in `validateAddition()`.
- **Do not put the checkout re-check in `onBasketValidated()`** - it fires after the Stripe session is created and the payment row persisted, so throwing there strands both.
- **Do not store a reference to a `ProductItem` in the basket** instead of flattened data.
- **Do not serve a bought file from where it was uploaded** — copy it per purchase, with a token and an expiry.
- **Do not put that copy under `public/`** — `private/downloads/` keeps the route the only way to it.
- **Do not check the expiry in the template alone** — `resolveFilePath()` refuses an expired token before any file is opened.
- **Do not leave a never-downloaded copy behind** — the purge goes by the expiry date, not by whether the link was clicked.
- **Do not email a link for a source file that no longer exists.**
- **Do not duplicate a `data-basket-target`** already carried by PaymentBundle's own button.
- **Do not emit the basket bar from a shop page** — `Basket:Navbar` is placed once in the site's layout, and a second one on the page is never filled.
- **Do not offer a stock alert on an item withdrawn from sale** (`limitedQuantity` at 0) — nobody is waiting for what is not coming back.
- **Do not send the alerts from a Doctrine listener** — it would fire inside the back-office flush and make the shopkeeper wait on the mailer.
- **Do not send a whole waiting list in one pass** — the command is batched on purpose.
- **Do not write the availability rule again** — read `isItemAvailable()` / `isItemSoldOut()`.
- **Do not drop a `data-*` attribute** from the add button.
- **Do not add a JavaScript controller to this bundle** for basket behaviour.
- **Do not issue a gift card anywhere but `onBasketPaid()`** — a basket abandoned before paying must mint nothing.
- **Do not issue one card for a quantity of three** — a unit bought is a card.
- **Do not read a card's worth off the price** — `giftCardValue` is its face value, sold at whatever the item costs.
- **Do not hand `issue()` a design read off the product** — read it off the basket entry, which copied it when the card was bought.
- **Do not read test mode from a Stripe key.**
- **Do not report a free article as an error** — a story given away is a business decision, and `#free-items` skips itself on a catalogue giving away more than it sells.
- **Do not read the undelivered check further back than a link lives** — the purge has taken the copies of older orders away, so every one of them would report as never delivered.
