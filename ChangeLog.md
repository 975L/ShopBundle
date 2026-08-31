# Changelog

## v2.5.0

Articles carry a shipping weight, and the graph prices it

- **New `ProductItem::$weight`**, the packed weight in whole grams, null on what the shop does not weigh (31/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- `ProductBasketItemProvider` implements PaymentBundle's `WeighableBasketItemProviderInterface` (31/08/2026)
- A line weighs its article as many times as it was ordered (31/08/2026)
- A download, a service and a card sent by email weigh nothing (31/08/2026)
- An unweighed article counts as nothing rather than as zero (31/08/2026)
- The weight travels in the export and the import archives (31/08/2026)
- Duplicating a product carries the weight of its items (31/08/2026)
- New `label.weight` and `label.weight_help` in the three locales (31/08/2026)
- The JSON-LD publishes the rate the delivery grid charges for the item's own weight, and names the country it is priced for (31/08/2026) [BC-Break]
- No `shippingDetails` at all for an unweighed article, a shop naming no default country, or a silent grid (31/08/2026)
- Requires `c975l/payment-bundle` `^6.5` (31/08/2026)
- `ProductAffinity::$affinityScore` declares its default as a string, every `make:migration` regenerating the same `ALTER` (30/08/2026)
- The showcase slider leafs through the photographs of the first sample product, keyed `shop/<slug>` (31/08/2026)
- Failing those, the generic pool is rotated as before (31/08/2026)
- Requires `c975l/core-bundle` `^1.19.2` for `BlockFixtureMediaAttacher::placeholderImagesFor()` (31/08/2026)
- New `sass/block-thumbs.scss`, one silhouette per block kind (30/08/2026)
- `StylesheetProvider` hands those silhouettes to the back-office block picker (30/08/2026)
- `CalculateProductAffinityCommand`, `ProductImportProvider` and `ProductBasketItemProvider` split into smaller methods (30/08/2026)
- New `phpmd.xml.dist`, and `composer qa` runs `mess` and `lizard` (31/08/2026)
- The `mess` script prints the files PDepend could not parse (31/08/2026)
- `.codacy.yaml` excludes the repository root's own `public/`, `tests/` and `vendor/` (31/08/2026)
- One tile per block kind pictured in the README (31/08/2026)
- The four shop skills cover the weight, the shipping graph and the silhouettes (31/08/2026)
- PHPStan ignores the `phpDoc.parseError` PHPMD's `@SuppressWarnings` marker raises (31/08/2026)
- Added `tests/Command/CalculateProductAffinityCommandTest.php` and `tests/Service/StylesheetProviderTest.php` (30/08/2026)

## v2.4.0

The published switch is turned round into hidden

- `Product::$isPublished` and `ProductItem::$isPublished` become `hidden`, `isHidden()`/`setHidden()` replacing `isPublished()`/`setIsPublished()` (30/08/2026) [BC-Break] **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- `Product::getPublishedItems()` becomes `getVisibleItems()`, `product.visibleItems` in Twig (30/08/2026) [BC-Break]
- `ProductRepository::findOnePublishedBySlug()` becomes `findOneVisibleBySlug()` (30/08/2026) [BC-Break]
- A new product starts hidden, the column defaulting to false so an existing catalogue stays online (30/08/2026)
- The sheet of a hidden product answers 404, the recycle bin still answering 410 (30/08/2026)
- A hidden product leaves the listings, the search, the recommendations, the blocks, the basket, the stock alerts and the sitemap (30/08/2026)
- A hidden item leaves the sheet, the offers of the structured data and the basket (30/08/2026)
- `hidden` replaces `isPublished` in the export archives (30/08/2026)
- An archive carrying `isPublished` is imported the same way (30/08/2026)
- `label.hidden`, `text.hidden` and `label.product_item_hidden_help` replace their published counterparts in the three locales (30/08/2026)
- The guided project points at `#Product_hidden`, its two step keys renamed (30/08/2026)
- The guided step announcing the save no longer says the sheet goes online published (30/08/2026)
- The comments, the README and the three shop skills say "hidden" where they said "draft", and tell the switch apart from the recycle bin (30/08/2026)
- The products and the categories screens carry their own EasyAdmin labels, the class name showing otherwise (30/08/2026)
- `ShopDemoFixtureProvider` seeds a product with the pictures a site declares for it, keyed `shop/<slug>` (28/08/2026)
- Each seeded picture carries its `position`, the order the sheet's slider leafs through them (28/08/2026)
- Failing a declared picture, the generic pool is rotated as before (28/08/2026)
- Requires `c975l/core-bundle` `^1.19` (28/08/2026)
- Added `tests/Repository/ProductRepositoryTest.php` and `tests/Management/ShopSitemapProviderTest.php` (30/08/2026)

## v2.3.0

A demo site is handed the very catalog the showcase renders

- "Export selection" now leads the products' and the categories' batch bars, delete reading last (28/08/2026)
- New `ShopSampleCatalog`, the made-up catalog held once as plain data: six products over three categories (28/08/2026)
- New `ShopDemoFixtureProvider`, implementing `DemoFixtureProviderInterface` for a demo site's own loader (28/08/2026)
- The demo fixtures yield the categories and the products only, leaving a site's own shop content alone (28/08/2026)
- Media are seeded from a temporary copy of the site's placeholders, never the placeholders themselves (28/08/2026)
- The copy is handed to VichUploader as a `ReplacingFile`, a plain `File` being silently ignored (28/08/2026)
- Each sample product carries a written-down creation date (28/08/2026)
- `ProductListener` and `ProductItemListener` only stamp a creation date on an entity carrying none (28/08/2026)
- `ShopShowcaseProvider` reads its stand-ins off `ShopSampleCatalog` instead of numbering them (28/08/2026)
- The `label.shop_showcase_product_*`, `_category_*` and `_item_*` keys are replaced by `label.shop_sample_*` ones (28/08/2026)
- Requires `c975l/core-bundle` `^1.18` for `DemoFixtureProviderInterface` (28/08/2026)
- The `c975l-shop-catalog` and `c975l-shop-blocks` skills cover the sample catalog (28/08/2026)
- Added `tests/Listener/ProductCreationDateTest.php`, `tests/Service/ShopSampleCatalogTest.php` and `tests/Service/ShopDemoFixtureProviderTest.php` (28/08/2026)

## v2.2.1

Logo modified

## v2.2.0

The shop pages its listing without KnpPaginatorBundle

- `ShopService::findAllProductsPaginated()` and `ProductService::findAllPaginated()` return UiBundle's `Pagination` (25/08/2026) [BC-Break]
- `knplabs/knp-paginator-bundle` leaves the requirements (25/08/2026) [BC-Break]
- `composer.json` requires `c975l/core-bundle` `^1.17.2`, the version naming `Paginator` (25/08/2026) [BC-Break]
- The listing's templates are untouched, `Pagination` answering the figures and the url they read (25/08/2026)
- `UPGRADE.md` states the type the two services return now (25/08/2026)
- Every structured data block is printed with `raw`: stored in a variable first, the graph lost the function's own `is_safe` marking and was served escaped, which no parser reads (25/08/2026)

## v2.1.3

The favorite heart is left off a card standing for no stored product

- `Product.html.twig` draws the favorite button only on a product carrying an id (24/08/2026)
- `ShopShowcaseProvider` states why its stand-ins go without one (24/08/2026)

## v2.1.2

UPGRADE.md entries are filed under the version they shipped in

- `UPGRADE.md` names the pending section `### v2.1.1` rather than `### Unreleased` (24/08/2026)
- `UPGRADE.md` files the entries shipped in v2.0.0 under one `### v2.0.0` heading (24/08/2026)

## v2.1.1

The affinity score is held as the number it is read back as

- `ProductAffinity::$affinityScore` is mapped `Types::FLOAT` rather than `decimal(5, 2)` (24/08/2026)
- `doctrine:schema:validate` no longer reports the mapping as invalid (24/08/2026)
- `UPGRADE.md` states the migration the column change asks for (24/08/2026)

## v2.1.0

Weekly integrity checks on the catalogue and the deliveries

- Added `ShopIntegrityHealthCheckProvider`, four weekly checks under the `shop-integrity` kind (24/08/2026)
- They report a paid order whose files were never copied nor sent, a file on sale missing from the server, an article ordered past its stock, and an article on sale for nothing (24/08/2026)
- The free-article check is a warning, and skips itself on a catalogue giving away more than it sells (24/08/2026)
- Each check is guarded on its own, `HealthCheckRunner` dropping every row of a provider that throws (24/08/2026)
- Orders are read no further back than a download link lives, and one settled within the hour is left alone (24/08/2026)
- Added `ShopIntegrityHealthCheckAdviceProvider`, listing the articles and the orders behind each count, one link apiece (24/08/2026)
- Added `ProductItemRepository::findSellable()` and `ProductItemDownloadRepository::findDeliveredBasketIds()` (24/08/2026)
- Added the `label.health_check_shop_*` and `label.health_check_advice_shop_*` keys to the `shop` catalogs (24/08/2026)
- Requires `c975l/payment-bundle` `^6.2`, which `BasketRepository::findOrdersSince()` comes from (24/08/2026)
- Requires `c975l/core-bundle` `^1.16`, which `ui_reviews_section()` comes from (24/08/2026)
- The product sheet's reviews are drawn by `ui_reviews_section()`, cached with the sheet's blocks (24/08/2026)
- The product sheet's gallery is capped at 420px and centred (24/08/2026)
- An item's fields no longer carry a `placeholder`, their label saying what is asked (`ProductItemType`) (24/08/2026)

## v2.0.0

Extract checkout, crowdfunding and lottery into their own bundles

- Collapsed to one line the stacked comments of `ProductItemDownloadMessageHandler`, `ProductReviewVerifier` and `ProductSnippetBuilder` (24/08/2026)
- A limited quantity now reads as three states, `limitedQuantity` defaulting to null instead of 0 (23/08/2026)
- Added `label.out_of_stock` and `label.limited_quantity_help` to the `shop` catalogs (23/08/2026)
- Dropped `label.limited_quantity_reached` from the `shop` catalogs, left behind by the crowdfunding move (23/08/2026)
- `findPending()` leaves out the alerts whose item is not buyable again, which used to fill the batch run after run (23/08/2026)
- The price bands are cut from the highest "from" price, the one the filter compares them against (23/08/2026)
- `blocks/Products.html.twig` no longer emits a second `id="products"` (23/08/2026)
- `ProductDuplicator` carries the gift-card value, text and scratch panel over to the copy (23/08/2026)
- `ProductItemStockAlertController` arms the anti-bot timer before the render, a correction after a typo no longer being read as a bot (23/08/2026)
- `onBasketPaid()` reads the download flag and the gift-card amount off the frozen basket, not off the live catalogue (23/08/2026)
- `ProductSnippetBuilder` encodes with `JSON_INVALID_UTF8_SUBSTITUTE`, a stray byte no longer emptying a sheet's structured data (23/08/2026)
- The shop's range caption counts what has been shown, not what the current page holds (23/08/2026)
- A visitor can ask to be told when a sold-out item is back, the address kept against the item and dropped by the link its own email carries (23/08/2026)
- Added `ProductItemStockAlert`, its repository and `ProductItemStockAlertService` (23/08/2026)
- Added `c975l:shop:stock-alerts:send`, scheduled hourly by `ShopMaintenanceTaskProvider` and bounded by `--limit` (23/08/2026)
- Added `ShopEmailTemplateProvider`, this bundle's first, declaring the `back_in_stock` template in the three locales (23/08/2026)
- `AddButton.html.twig` offers the subscription only where the stock ran out, never where the item was withdrawn (23/08/2026)
- `ProductStateServiceInterface` gains `isItemAvailable()` and `isItemSoldOut()`, the rule its card, its button and the alerts now share (23/08/2026)
- `c975LShopBundle` declares the `shop_stock_alert` rate limiter, so the public route is never served unlimited (23/08/2026)
- `composer.json` requires `symfony/rate-limiter` `^8.1`, now read directly (23/08/2026)
- Added `public/icons/bell.svg` (23/08/2026)
- Added `label.back_in_stock_*`, `label.stock_alert_*` and `text.stock_alert_*` to the `shop` catalogs (23/08/2026)
- Added `shop_item_sold_out()`, so the add button reads the rule rather than comparing the two columns itself (23/08/2026)
- Added `tests/Service/ProductItemStockAlertServiceTest.php`, `tests/Controller/ProductItemStockAlertControllerTest.php` and `tests/Email/ShopEmailTemplateProviderTest.php`, and extended `ProductStateServiceTest` (23/08/2026)
- `UPGRADE.md` states the new table (23/08/2026)
- Added a `config/whatsnew.json` entry for the back-in-stock alert (23/08/2026)
- A product's sheet shows the reviews buyers left on it, and the link to leave one - gated on `ui-enable-reviews`, before the recommendations that send the reader elsewhere (23/08/2026)
- Added `ProductReviewVerifier`: a review is marked verified when its address paid for one of the product's items, asked of `Basket::holdsItem()` so how an order stores its lines stays PaymentBundle's business (23/08/2026)
- `ProductCrudController::deletePermanently()` drops a product's reviews along with its ratings and the wishlists it was on (23/08/2026)
- Requires `c975l/core-bundle` ^1.15, which is where the reviews live (23/08/2026)
- Requires `c975l/payment-bundle` ^6.1, for the orders a verified review is checked against (23/08/2026)
- The five public pages no longer set an hour of browser cache of their own (23/08/2026)
- `ProductBasketItemProvider` reads an order's stored lines with defaults: one placed before this bundle knew about services or files no longer breaks what displays it (23/08/2026)
- The download email is sent through `BasketEmailSender`, so it is written in the language the order was placed in (23/08/2026)
- Added `giftCardText` and `giftCardScratch` to `Product`, and `Product::isGiftCard()` answering off its items (23/08/2026)
- The product form gained a `Gift card` fieldset, and the export/import carry the two fields (23/08/2026)
- `ProductBasketItemProvider` copies the visual onto the basket and hands it to `GiftCardService::issue()` as a `GiftCardDesign` (23/08/2026)
- Added the `shop_gift_cards` block kind, its `GiftCardsBlockType`, its template and its showcase (23/08/2026)
- Added `ShopBlockExtension::getGiftCards()` and the `shop_block_gift_cards()` Twig function (23/08/2026)
- `ShopBlockCacheTagProvider` drops the new kind under the catalog tag, a card being a product (23/08/2026)
- Added `sass/_gift-cards.scss`, the offer laid out around PaymentBundle's own card (23/08/2026)
- Added `label.gift_card_design`, `label.gift_card_design_help`, `label.gift_card_text`, `label.gift_card_text_help`, `label.gift_card_scratch`, `label.gift_card_scratch_help`, `label.block_gift_cards`, `label.block_gift_cards_description`, `label.block_max_gift_cards` and `label.shop_showcase_gift_card_text` to the `shop` catalogs (23/08/2026)
- Extended `ProductGiftCardTest`, `ShopBlockExtensionTest`, `BlockTypesTest`, `ShopBlockCacheTagProviderTest` and `ShopShowcaseProviderTest` (23/08/2026)
- README and the `c975l-shop-checkout`, `c975l-shop-blocks` and `c975l-shop-catalog` skills state the visual and the block that sells it (23/08/2026)
- Added `tests/Template/TwigSyntaxTest.php`, guarding against the "for ... if" modifier Twig 3 dropped (23/08/2026)
- `UPGRADE.md` states the two new columns of `shop_product` (23/08/2026)
- The terms page reads `legal_document_html()`, so it serves the version the site customized rather than the model as shipped (23/08/2026) [BC-Break]
- The terms are offered as a file through CoreBundle's own `ui_legal_document_pdf`, which works whether SiteBundle is installed or not (23/08/2026)
- Added `label.terms_of_sales_pdf` to the `shop` catalogs (23/08/2026)
- `composer.json` requires `c975l/core-bundle` `^1.15`, the version naming `PdfGeneratorInterface` (23/08/2026) [BC-Break]
- Added a `config/whatsnew.json` entry for the gift card visuals (23/08/2026)
- `c975l/core-bundle` raised to `^1.14`, `ShopShortcutProvider` reading `CATEGORY_TOGGLE` (22/08/2026)
- The customer area hands out only the copies the delivery made, never a fresh one, so a bought file lives exactly as long as its email says (22/08/2026)
- `ProductItemDownloadServiceInterface::liveTokensByItem()` becomes `liveByItem()`, returning the rows rather than their tokens (22/08/2026) [BC-Break]
- A download handed to the customer area carries its expiry date (22/08/2026)
- One case added to `tests/Service/ProductBasketDownloadProviderTest.php` (22/08/2026)
- Collapsed to one line the two-line comments of the sources this lot adds (22/08/2026)
- The five `// @param` slips are written as the docblocks the rest of the bundle carries (22/08/2026)
- `Product::getItems()` and `getMedias()` carry the `Collection<int, ...>` their siblings do (22/08/2026)
- Added an import case on an archive naming no limited quantity (22/08/2026)
- README and the seo skill state the `ItemList` the shop's index and a category page publish (22/08/2026)
- README and the checkout skill state that `Shop:TestMode` speaks for the catalog alone (22/08/2026)
- The checkout skill states `liveTokensByItem()` and names PaymentBundle's own skills (22/08/2026)
- `Shop:TestMode` no longer calls PaymentBundle's banner (22/08/2026)
- Removed `sass/_typography.scss`, its `.delete-link` rule moved to PaymentBundle (22/08/2026)
- `.shop-counters-separator` reads on `--shop-text-muted` (22/08/2026)
- `Shop:NavbarBasket` drops the `icon` attribute of the basket button (22/08/2026)
- `ProductBasketItemProvider` writes the tax held in a line's price, through PaymentBundle's `VatCalculator::included()` (22/08/2026)
- Added a case to `ProductBasketItemProviderTest` (22/08/2026)
- Importing an item without a limited quantity brings it back on sale, no longer as out of stock (22/08/2026)
- A category's JSON-LD lists the products the page shows, drafts and trashed ones left out (22/08/2026)
- `ProductItemDownloadServiceInterface::liveTokensByItem()` replaces `ProductBasketDownloadProvider`'s private one (22/08/2026)
- `ProductItemDownloadService::recordDownloaded()` guards against a basket already gone (22/08/2026)
- Moved the reuse cases of `ProductBasketDownloadProviderTest` to `ProductItemDownloadServiceTest` (22/08/2026)
- Aligned `.codacy.yaml`, `.stylelintrc.json` and `bin/ci.sh` on CoreBundle's (22/08/2026)
- Added `eslint.config.mjs` and `.markdownlint.json`, the two lint configurations CoreBundle carries (22/08/2026)
- Rector caches in `.rector.cache` inside the repository, no longer in the directory shared by every repo - `composer rector` drops `--clear-cache` (22/08/2026)
- The dist archive drops every dev-only file, `/tests`, `/bin` and the tool configurations included (22/08/2026)
- `phpstan.dist.neon` carries the shared `trait.unused` ignore (22/08/2026)
- Added the `audit-deps` description to `scripts-descriptions` (22/08/2026)
- A product item can be sold as a gift card, `ProductItem::$giftCardValue` carrying its face value (22/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- A paid gift card item issues one card per unit bought, through PaymentBundle's `GiftCardService` (22/08/2026)
- Such an item carries `Basket::CONTENT_FLAG_GIFT_CARD` on top of how it is delivered (22/08/2026)
- A gift card value travels with a product export/import (22/08/2026)
- Added `label.gift_card_value` and its help text to the three `shop` catalogs (22/08/2026)
- Added `ProductGiftCardTest` (22/08/2026)
- Raised the `c975l/payment-bundle` requirement to `^6`, for `GiftCardService` (22/08/2026)
- `c975l/config-bundle`, `c975l/site-bundle` and `c975l/sharebuttons-bundle` are replaced by `c975l/core-bundle` (22/08/2026) [BC-Break]
- Added `WhatsNewProvider` and `config/whatsnew.json`, feeding the dashboard's what's new (22/08/2026)
- `Product` declares `\Stringable`, which its `__toString()` did not (22/08/2026)
- Rebuilt the stylesheets with `--no-charset`, the minified one carrying a BOM that drops the rule following it (22/08/2026)
- Translated to English the comments of `sass/_categories.scss` and `components/Product/Refine.html.twig` (22/08/2026)
- `ShopCacheInvalidationListener` matches the abstract `Media`, an item's picture and its bought file leaving the cached items table untouched (22/08/2026)
- Added `ProductJsonLdClientTest`, `ProductStructuredDataHealthCheckProviderTest`, `ShopCacheInvalidationListenerTest` and `ShopShowcaseProviderTest` (22/08/2026)
- Added `isPublished` to `ProductItem`, an item taken offline leaving the sheet, the graph and the basket (22/08/2026)
- Added `Product::getPublishedItems()`, `getItems()` still holding them all for the back-office (22/08/2026)
- A product export/import and `ProductDuplicator` carry `isPublished`, an archive written before the column coming back on sale (22/08/2026)
- `ProductRepository::findMaxItemPrice()` skips the items taken offline, which stretched the listing's price bands over prices nothing carried (22/08/2026)
- The breadcrumb is a wrapping row of levels, a chevron ending up alone at the start of a line on a phone (22/08/2026)
- Removed `<twig:c975LShop:Shop:ViewButton/>` from the product sheet and the category page, the breadcrumb saying it (22/08/2026)
- The rating and the favorite heart sit on one centred row on the product sheet (22/08/2026)
- "In stock" is only printed on a physical item (22/08/2026)
- The two stock colours are mixed with `--text`, unreadable in dark mode (22/08/2026)
- Added `Product::__toString()` (22/08/2026)
- The favorite heart moves onto the picture, opposite the badge, where it costs the card no row of its own - 24px back on a phone card (22/08/2026)
- `ProductCategory` and the new single-row `ShopSettings` carry blocks, so a category page and the shop's index are composed in the back-office (22/08/2026)
- Added `ShopSettingsCrudController`, the dashboard's "Shop page" screen, and its `MenuProvider` entry (22/08/2026)
- `ShopBlockOwnerResolver` and `ShopBlockEditUrlProvider` answer for the three owners, a block being draggable between them (22/08/2026)
- A category export/import now carries the blocks composed on its page (22/08/2026)
- Removed `<twig:c975LShop:Shop:Shipping/>` and `<twig:c975LShop:Shop:Footer/>`, their five labels and their `sass/_components.scss` rules - a block says it now (22/08/2026) [BC-Break]
- Added `ShopBlockOwnerResolverTest` (22/08/2026)
- README and the two shop skills state the three pages composed in the back-office, overriding a template no longer being the way (22/08/2026)
- Removed `<twig:c975LShop:Shop:Information/>`, the shop's welcome message, its two labels and its `.shop-information` rules - a block says it now (22/08/2026) [BC-Break]
- Added `ProductBasketDownloadProvider`, PaymentBundle's `BasketDownloadProviderInterface` (22/08/2026)
- Added a `productItemId` column to `ProductItemDownload` (22/08/2026)
- Added `getFileItems()` to `ProductItemDownloadService`, shared by the email handler and the customer area (22/08/2026)
- Added `ProductStructuredDataHealthCheckProvider` and `ProductJsonLdClient`, health check kind `product-json-ld` (22/08/2026)
- Added `tests/ConfigsJsonTest.php` (22/08/2026)
- Removed the 152 translation keys left by basket, lottery, crowdfunding and payment (22/08/2026)
- Normalised the six `.xlf` ids that were not derived from their key (22/08/2026)
- Removed `CardComponent` and `SliderComponent`, replaced by the `shop_product` and `shop_product_slider` block kinds (22/08/2026) [BC-Break]
- README states the three configuration keys the bundle declares, and documents `shop-favorites` (22/08/2026)
- `c975l-shop-seo` no longer lists the four status values `PaymentStatusProvider` carries (22/08/2026)
- The shop's index is written for a phone first, its filters folded into `<twig:c975LShop:Product:Refine/>` (21/08/2026)
- The panel opens by itself and carries the count of what narrows the listing whenever the visitor arrives on a filtered one (21/08/2026)
- Every filter says its name above itself instead of inside its first option, that option now reading "All": the name written in the option only disappeared the moment a value was picked (21/08/2026)
- The counters move from above the filters to just above the grid they count, where filtering visibly changes them (21/08/2026)
- The category selector drops its "select a category" option, which was never the selected one, and the category page hands it the category being read - it showed the whole shop while standing on a category (21/08/2026)
- `<twig:c975LShop:Product:Products/>` takes an `id`, the live search passing none: its results printed a second `id="products"` and a second "All our items" heading over the very listing of the page (21/08/2026)
- Removed `phpstan-baseline.neon`, as CoreBundle has none: what it hid is either fixed below or a motivated exception in `phpstan.dist.neon` (21/08/2026)
- `scoreByPrice()` guarded against a free product with `0 === $price` on a float, which a strict comparison never matches - the division it protects was reachable (21/08/2026)
- Removed the dead `0 === $maxPossibleScore` guard of `scoreByCoPurchase()`, its loop always running at least once (21/08/2026)
- `MediaService`, `ProductService`, `ProductItemService` and `ProductItemDownloadService` drop the magic finders for `find()` and `findOneBy()` (21/08/2026)
- `ProductCategoryController` no longer injects the service it never read, its route reading its entity through `#[MapEntity]` (21/08/2026)
- `ProductItemService` no longer injects the entity manager nor the media repository, neither being read (21/08/2026)
- `ProductItem::$currency` and `$vat` drop the null their NOT NULL columns never hold (21/08/2026)
- `ShopSitemapProvider` drops the `?? []` on two `findAll()`, which answer an array (21/08/2026)
- The product search says when it is searching: `<twig:c975LUi:Search:Busy/>` sits under its field, the same sign every live search of the bundles now gives (21/08/2026)
- `ProductBasketItemProvider::onBasketValidated()` returns `[]` (21/08/2026) [BC-Break]
- `ProductBasketItemProvider::onBasketPaid()` takes PaymentBundle's third `$checkoutData` argument (21/08/2026) [BC-Break]
- `ShopStatusProvider` no longer reports `testMode`, `ordersToShip`, `oldestOrderToShip` and `stalledPayments`, now carried by `PaymentStatusProvider` (21/08/2026) [BC-Break]
- The provider keeps its four catalog counts, which are its own (21/08/2026)
- `ShopShowcaseProvider` builds real `Product` and `ProductItem` stand-ins, the four product kinds of the showcase rendering instead of raising a `TypeError` (22/08/2026)
- `ProductSnippetBuilder` publishes `OfferShippingDetails` again, its file test reading the name rather than the empty placeholder every item carries (22/08/2026)
- `ProductCrudController::reorder()` writes the new slots by query on a read-only catalog, no longer restamping the modification date and the author of every product (22/08/2026)
- `ShopStatusProvider` counts the products carrying no description at all among the thin ones (22/08/2026)
- The same provider no longer counts the empty media of a new item as an image without alternative text (22/08/2026)
- `Media::getMimeType()` answers `video/ogg` for an `.ogv` file (22/08/2026)
- The category page lists the published products of its category, sorted by position, rather than its raw association (22/08/2026)
- `ShopBlockExtension` keys its per-request product cache on the naming too, a preview no longer handing its draft to a block that named it (22/08/2026)
- The per-purchase copy of a bought file moves from `public/downloads/` to `private/downloads/`, so the download route is the only way to it (21/08/2026)
- The route refuses an expired token itself (`ProductItemDownloadService::resolveFilePath()`), rather than relying on the nightly command having erased the copy (21/08/2026)
- A download is recorded once the response is built, and no longer for a copy that turned out to be missing (21/08/2026)
- `c975l:shop:downloads:delete` no longer waits for a link to have been clicked: a copy nobody ever opened stayed on the disk for good (21/08/2026)
- The same command drops the rows expired for more than 30 days, which nothing purged before, and delegates to the new `ProductItemDownloadServiceInterface::purgeExpired()` (21/08/2026)
- A link that ran out without ever being used no longer claims it was downloaded, and the contact button of `item_downloaded.html.twig` only shows where ContactFormBundle is installed (21/08/2026)
- Added `label.download_link_expired` and `text.download_link_expired` to the three `shop` catalogs (21/08/2026)
- Added `ProductItemDownloadServiceTest` and rewrote `ProductItemDownloadControllerTest` (21/08/2026)
- The catalogue joins the sync: products and categories are carried by "export sync all" and read back by the import button (20/08/2026)
- Added `ProductExportProvider`, `ProductImportProvider`, `ProductCategoryExportProvider` and `ProductCategoryImportProvider` (20/08/2026)
- A product travels whole: its pictures, its items with the files they are bought for, its blocks, its categories and its related products (20/08/2026)
- An imported file is laid back under the name it was served under, so a synced catalogue keeps its image urls (20/08/2026)
- Added an "export selection" action to the products and the categories indexes (20/08/2026)
- Added `action.export_selection` to the three `shop` catalogs (20/08/2026)
- Added `ProductExportProviderTest`, `ProductImportProviderTest`, `ProductCategoryExportProviderTest` and `ProductCategoryImportProviderTest` (20/08/2026)
- A basket is revalidated at checkout, before anything is numbered or charged (`ProductBasketItemProvider::validateCheckout()`, requires PaymentBundle's new contract method) (20/08/2026)
- A basket holding more of an item than is left no longer checks out, which `validateAddition()` alone could not see (20/08/2026)
- A product item carries a previous price, struck through beside its own with a discount badge on the card (20/08/2026)
- A previous price not above the price publishes nothing, on the card as in the graph (20/08/2026)
- The listing narrows on price, format and availability, through `?price=`, `?format=` and `?stock=` (20/08/2026)
- The price bands offered are cut from the catalogue's own dearest item (20/08/2026)
- A product sheet and a category page open with their breadcrumb and publish it as `BreadcrumbList` (20/08/2026)
- The products an editor picks for a product replace its calculated recommendations (20/08/2026)
- The graph carries the product's brand and each item's reference and barcode (20/08/2026)
- An item on offer publishes its previous price as a `ListPrice` price specification (20/08/2026)
- A duplicated product keeps its brand and previous prices, and takes neither reference nor barcode (20/08/2026)
- Added `Product::$brand` and `$relatedProducts`, `ProductItem::$priceBefore`, `$sku` and `$gtin`, which needs a migration (20/08/2026)
- Added `ShopBreadcrumbBuilder`, `ProductSnippetBuilder::buildBreadcrumb()` and `ProductRepository::findMaxItemPrice()` (20/08/2026)
- Added `ShopService::getFilters()` and `getPriceBrackets()` (20/08/2026)
- Added `ProductStateService::getItemPriceBefore()` and `getItemDiscount()`, and the Twig functions `shop_item_price_before()`, `shop_item_discount()`, `shop_breadcrumb()` and `shop_breadcrumb_json_ld()` (20/08/2026)
- Added `templates/components/Shop/Breadcrumb.html.twig` and `templates/components/Product/Filters.html.twig` (20/08/2026)
- `Product:Sort` takes the active filters, so reordering a listing no longer widens it (20/08/2026)
- Added `label.brand`, `label.breadcrumb`, `label.discount`, the six `label.filter*`, `label.gtin`, `label.price_before`, `label.related_products` and `label.sku`, with their help texts, to the three `shop` catalogs (20/08/2026)
- Added `ProductRecommendationServiceTest` (20/08/2026)
- The product sheet carries its customers' ratings, behind the new `shop-rating` setting, on by default (20/08/2026)
- `ProductCrudController::deletePermanently()` drops the ratings of the product it removes (20/08/2026)
- Added `ProductRatingTest` (20/08/2026)
- The basket bar drops the `black` class it passed to the view button, no such class existing in the stylesheets (20/08/2026)
- A product is a draft until it is published, out of the shop, of the search, of the sitemap and of the blocks naming it (20/08/2026)
- Deleting a product moves it to the recycle bin, its url answering 410 until it is restored or removed for good (20/08/2026) [BC-Break]
- Renaming a product redirects its old url to the new one, deleting it for good leaves a `gone` redirect behind (20/08/2026)
- A draft's sheet is read through the new `product_preview` route, uncached and admin-only (20/08/2026)
- A product is duplicated from its own page and from the index, with its pictures, its items and the blocks of its sheet - the copy is a draft (20/08/2026)
- The items of a draft or trashed product can no longer be added to a basket (20/08/2026)
- Added `Product::$isPublished` and `Product::$isDeleted`, which needs a migration (20/08/2026)
- Added `ProductDuplicator`, and the `duplicate`, `preview`, `trash`, `restore` and `deletePermanently` actions of the product crud (20/08/2026)
- Added `ProductRepository::findOnePublishedBySlug()`, `findNotDeleted()` and `findMaxPosition()` (20/08/2026)
- Added `action.delete_permanently`, `action.duplicate`, `action.move_to_trash`, `action.preview`, `action.restore`, `action.trash`, the three `confirm.*`, `flash.product_deleted_permanently`, `flash.product_duplicated`, `flash.product_restored`, `label.copy_suffix`, `label.preview_mode`, `label.published` and `text.published` to the three `shop` catalogs (20/08/2026)
- Removed `templates/components/Product/Icon.html.twig`, `bag-shopping.svg` and the `file-*.svg` icons (20/08/2026) [BC-Break]
- An open product item states its file format as a badge under its picture (20/08/2026)
- The add button of an open item sits on the left, the theme's auto margins no longer centering it (20/08/2026)
- Added `ProductStateService::getItemFileFormat()` and the Twig function `shop_item_file_format()` (20/08/2026)
- `Shop:TestMode` warns for the catalog alone and delegates the charge to `Basket:TestMode`, the two flags reading as two banners (20/08/2026)
- `label.test_mode` speaks of the orders, not of the charge (20/08/2026)
- Removed `label.secure_payments` from the three `shop` catalogs, PaymentBundle owning it (20/08/2026) [BC-Break]
- The product items no longer draw the basket count PaymentBundle's `Item:Quantity` carried, which only the basket page refreshed (20/08/2026)
- The basket bar takes the shop's own `--primary` band instead of the ecosystem's dark surface (20/08/2026)
- The basket bar publishes its height in `--bottom-bar-height`, what a site fixes to the bottom corners stepping over it (20/08/2026)
- The recommendations sit on a `--primary` band, each row taking the page's own ground back (20/08/2026)
- The recommendations wrap centered, so a row left alone at the end no longer hangs on the left (20/08/2026)
- Removed the continue shopping link from the recommendations header (20/08/2026)
- `ProductItemListener` and `ProductItemDownloadService` stamp their dates with a `DateTime`, the columns being mutable (20/08/2026)
- Removed the detail action from the product and category cruds (20/08/2026)
- Added the `viewOnSite` action to the product and category cruds (20/08/2026)
- Added `action.view_on_site` to the three `shop` catalogs (20/08/2026)
- The product items form declares the `shop` domain, and the slug and collection fields get their label (20/08/2026)
- Added `label.slug` to the three `shop` catalogs (20/08/2026)
- An editor hovering a product card, a category page or a section of a product sheet gets a button to the field or the object it stands for (20/08/2026)
- Added `ShopBlockEditUrlProvider`, `ShopEditUrlExtension`, the Twig functions `shop_product_edit_url()` and `shop_product_category_edit_url()`, and `ProductRepository::findByBlockIds()` (20/08/2026)
- Added `label.media` to the three `shop` catalogs (20/08/2026)
- Removed `templates/components/ProductItem/Quantity.html.twig` and `sass/_links.scss` (20/08/2026)
- Rebuilt the shop index and the product sheet, mobile first (20/08/2026)
- The pictures and the decision column of a product sheet part by `--section-space` when stacked (20/08/2026)
- Product cards state their category, excerpt, formats, price and availability badge (20/08/2026)
- Added `ProductStateService` and the Twig functions `shop_product_state()` and `shop_item_format()` (20/08/2026)
- The listing orders by `?order=newest|price_asc|price_desc` (20/08/2026)
- The listing grows on scroll instead of turning pages (20/08/2026)
- Raised the `c975l/core-bundle` floor to `^1.12.7`, which ships the `infiniteScroll` controller (20/08/2026)
- Added `label.load_more` to the three `shop` catalogs (20/08/2026)
- Added `ShopListingInfiniteScrollTest` (20/08/2026)
- The listing states its number of products and categories (20/08/2026)
- The product sheet lays the pictures beside the items from 900px up (20/08/2026)
- The items of a product sheet are rows, only one open at a time (20/08/2026)
- Recommendations are rows instead of cards (20/08/2026)
- The basket bar sits on the theme's inverse surface instead of the primary colour (20/08/2026)
- The delivery band and the product surfaces follow dark mode (20/08/2026)
- `findAllSorted()` joins the categories and the items (20/08/2026)
- Added `label.new`, `label.sold_out`, `label.coming_soon`, `label.from_price`, `label.in_stock` and the five `label.sort*` to the three `shop` catalogs (20/08/2026)
- The `shop_search` block carries an optional title (20/08/2026)
- Added the four `skills/c975l-shop-*` agent skills, shipped in the package (20/08/2026)
- Added `tests/SkillsTest.php`, checking what those skills quote against the sources (20/08/2026)
- Added `tests/Service/ProductStateServiceTest.php` (20/08/2026)
- Added `tests/Service/ShopServiceTest.php` (20/08/2026)
- The products index reorders by dragging its rows (20/08/2026)
- Added `tests/Controller/Management/ProductCrudControllerTest.php` (20/08/2026)
- Added `shop-test-mode` and the `config/configs.json` declaring it (20/08/2026)
- Added the shop test mode tile to the dashboard, toggling that entry (20/08/2026)
- `Shop:TestMode` reads `shop-test-mode` and `payment-test-mode` instead of the word "test" in the Stripe secret key (20/08/2026)
- `ShopStatusProvider` reports `testMode` (20/08/2026)
- Added `label.shop_test_mode_enable`, `label.shop_test_mode_disable` and the two `flash.shop_test_mode_*` to the three `shop` catalogs (20/08/2026)
- Added `translations/site_config.*.xlf`, carrying the label and the description of `shop-test-mode` (20/08/2026)
- The products and the categories export as SQL/CSV/JSON from their CRUD index (20/08/2026)
- Every product picture carries an `alt`, filled in from the back-office (20/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- `ProductCategory` carries a `description`, the category page's own content and its meta description (20/08/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- The category page states its own name as `h1`, without the shop's generic heading (20/08/2026)
- The delivery terms and a link to the return policy are on the product sheet, not only on the listings (20/08/2026)
- The first card of a listing loads eagerly, being the page's largest image (20/08/2026)
- Each offer publishes the shop's shipping rate and a link to its return policy (20/08/2026)
- `ShopStatusProvider` reports the sheets with no picture, a thin description, a picture with no `alt` and the categories with no description (20/08/2026)
- Added `tests/Entity/MediaTest.php` (20/08/2026)
- Added `/shop/terms-of-sales`, rendering UiBundle's model when SiteBundle holds no page for it (20/08/2026)
- Added `tests/Controller/ShopControllerTest.php` (20/08/2026)
- Each item's offer carries its own description and picture in the product's schema.org graph (20/08/2026)
- `ProductItem` carries an `itemCondition`, published in its offer for Google's merchant listings (20/08/2026)
- Declared the Symfony packages `src/` imports and core-bundle does not bring: `asset`, `form`, `framework-bundle`, `http-foundation`, `string`, `validator` (20/08/2026)
- Added `tests/Twig/ProductJsonLdExtensionTest.php` (20/08/2026)
- Added eight block kinds, putting the catalog on any page composed in the back-office (20/08/2026)
- Added `ShopBlockExtension`, resolving what those kinds show live at render time (20/08/2026)
- Added `ShopBlockChoices`, the product and category lists their forms pick from (20/08/2026)
- Added `ShopBlockCacheTagProvider` and `ShopCacheInvalidationListener`, so those kinds are cached and dropped on a catalog change (20/08/2026)
- `c975l:shop:affinity:calculate` now invalidates the blocks reading its scores, its bulk `DELETE` firing no Doctrine event (20/08/2026)
- Added `ShopShowcaseProvider`, rendering the eight kinds in a block showcase, on the placeholder media the hosting site already declares (20/08/2026)
- The product sheet's slider, buy table and recommendations step aside when the sheet holds the block taking each over (20/08/2026)
- Added `shop_block_sheet_kinds()`, naming every kind a sheet holds, its containers' slots included (20/08/2026)
- `ProductCrudController` declares the `shop_product` block context, offering the two sheet-only kinds there alone (20/08/2026)
- Added `ProductRepository::findByCategorySlug()` (20/08/2026)
- The products grid, the categories row and the recommendations take an optional title (20/08/2026)
- Added `tests/Form/Block/BlockTypesTest.php`, `tests/Service/ShopBlockCacheTagProviderTest.php`, `tests/Service/ShopBlockChoicesTest.php` and `tests/Twig/ShopBlockExtensionTest.php` (20/08/2026)
- One theme file per bundle, concatenated into a single request (19/08/2026)
- Removed `c975l:shop:sitemaps:create` and `templates/sitemap.xml.twig`, duplicating ConfigBundle's `c975l:sitemaps:create` (19/08/2026) [BC-Break]
- `Product` implements `HasBlocksInterface`, its sheet composed in the back-office with UiBundle's kinds (19/08/2026)
- Added `ShopBlockOwnerResolver`, so a block can be dragged from one product to another (19/08/2026)
- Added `ProductSnippetBuilder` and the `product_json_ld()` Twig function, publishing the product's schema.org graph with its `offers` (19/08/2026)
- Added `ShopBackupPathProvider`, declaring `public/medias/shop` and `private/medias/shop` (19/08/2026)
- Added `ShopStatusProvider`, reporting the orders left to ship and the payments never confirmed (19/08/2026)
- Added `LinkableRouteProvider`, so the shop and its categories are selectable as SiteBundle menu targets (19/08/2026)
- Added `UrlMetadataProvider`, declaring `/shop` (19/08/2026)
- `ShopSitemapProvider` carries a `title` and a `description` on its product and category urls, feeding the site's `llms.txt` (19/08/2026)
- Removed `config/configs.json`, whose fifteen entries are declared by ConfigBundle and PaymentBundle (19/08/2026) [BC-Break]
- Removed `templates/emails/`, whose layout and stylesheet no email referenced (19/08/2026)
- Removed the duplicate `label.all_our_items` and the unused `label.basket_paid` from the translations (19/08/2026)
- Added `tests/Management/ManagementTargetsTest.php` and `tests/Service/ProductSnippetBuilderTest.php` (19/08/2026)
- Added the `audit-deps` script and its CI step (19/08/2026)
- Composer's archive cache is carried from one CI run to the next (17/08/2026)
- The workflow runs on a push to main and on pull requests only, under a cancelling `concurrency` group (17/08/2026)
- Dropped `COMPOSER_TOKEN` from the setup-php step (17/08/2026)
- The workflow's `GITHUB_TOKEN` is pinned to `contents: read` rather than inheriting the repository's default write permissions: the checkout is the only step that reads it (17/08/2026)
- The Codacy token is declared on the job rather than on its own step, where the step's `if` could not read it: the condition was always false, so the coverage was never uploaded (17/08/2026)
- The templates state their page summary as `summarySocialNetwork`, the name both layouts read since UiBundle's was aligned on SiteBundle's (13/08/2026)
- The `Standard Symfony` step, absent from the workflow though `.php-cs-fixer.dist.php` was there, now runs in the CI (03/08/2026)
- Added the `qa` Composer script and its steps, which the CI workflow now calls (03/08/2026)
- Added `bin/ci.sh`, replaying the CI checks on dependencies freshly resolved from Packagist (03/08/2026)
- Added `scaffold/assets/styles/themes/shop.css`, this bundle's own tokens (01/08/2026)
- Added `ShopMaintenanceTaskProvider`, declaring `c975l:shop:downloads:delete` and `c975l:shop:affinity:calculate` so a site installing this bundle no longer lists them in its own schedule (01/08/2026)
- Raised the `c975l/config-bundle` requirement to `^5.16`, for `MaintenanceTaskProviderInterface` (01/08/2026)
- `php` is now required in `>=8.4` instead of `>=8.0` (30/07/2026) [BC-Break]
- The `symfony/*` requirements are now constrained to `^8.0` instead of `*` (30/07/2026) [BC-Break]
- The `symfony/ux-*` requirements are now constrained to `^3.3` instead of `*` (30/07/2026)
- The third-party requirements left in `*` are now bounded on their installed version (30/07/2026)
- The `c975l/*` requirements are now bounded on their major (30/07/2026)
- `Product::$user` and `ProductItem::$user` are now typed `c975L\ConfigBundle\Contract\UserInterface` instead of `App\Entity\User` (30/07/2026) [BC-Break]
- `UserTrait` now assigns the logged-in user only when it implements `c975L\ConfigBundle\Contract\UserInterface` (30/07/2026)
- `ProductItemDownloadMessageHandler` now calls `find()` instead of Doctrine's `findOneById()` magic finder, which Doctrine ORM will drop (30/07/2026)
- Added `.codacy.yaml` and `phpcs.xml.dist` (30/07/2026)
- Applied PSR-12 to the codebase (30/07/2026)
- Added `.php-cs-fixer.dist.php`, applying the Symfony coding standards (30/07/2026)
- Added `phpstan.dist.neon`, running the static analysis at level 5 (30/07/2026)
- Added `phpstan-baseline.neon`, freezing the errors that predate the analysis (30/07/2026)
- Added the `CI` GitHub Actions workflow, running PSR-12, the static analysis, the tests and the coverage upload (30/07/2026)
- The local Codacy CLI now runs `eslint@9.39.5` (30/07/2026)
- Removed the `site-url` config entry and its translations, now declared by ConfigBundle (29/07/2026) [BC-Break]
- Added `ShopSitemapProvider` (ConfigBundle's `SitemapProviderInterface`), so the shop/products/categories are declared in the site's `sitemap-index.xml` (26/07/2026)
- `c975l:shop:sitemaps:create` now takes its urls from `ShopSitemapProvider` instead of building them itself (26/07/2026)
- Added the Codacy grade badge to the README (30/07/2026)
- Index-page inline row actions (Edit/Delete/Detail) now show icon-only with the label as hover title, via ConfigBundle's `EasyAdminActionHelper::toIconOnly()` (16/07/2026)
- Removed dependency on SiteBundle, emails now use a self-contained layout (22/07/2026)
- Removed unused dependency on ShareButtonsBundle, added missing explicit dependency on UiBundle (22/07/2026)
- Extracted Basket/Payment/Stripe checkout into PaymentBundle, same table names, no data migration (22/07/2026)
- Extracted Crowdfunding/Lottery into CrowdfundingBundle, same table names, no data migration (22/07/2026)
- Added `ProductBasketItemProvider`, plugging products into PaymentBundle's basket (22/07/2026)
- Removed IsGranted[ROLE_ADMIN] to use the config value of ConfigBundle (07/06/2026)
- Made collections sortable by drag and drop (26/06/2026)
- Added prefix c975l: for each command (26/06/2026)
- Moved icons to their own folder (27/06/2026)
- Added a controller.sj to register stimulus controllers (27/06/2026)
- Updated Readme (27/06/2026)
- Replaced calls to Twig components c975LSite: by c975LUi: (27/06/2026)
- Suppressed media delete command as replaced by a Listener (28/06/2026)

## v1.12

- Removed shop dashboard and moved it to use the one of ConfigBundle (07/06/2026)
- Made use of new naming scheme of ConfigBundle (22/06/2026)

## v1.11.4

- Made the whole product card a link (07/06/2026)
- Added automatic slider display (07/06/2026)

## v1.11.3

- Corrected error for pagination parameter null value (06/06/2026)

## v1.11.2

- Corrected problem on invalid pagination parameter p value (06/06/2026)

## v1.11.1.2

- Added javascript copyrights (16/05/2026)

## v1.11.1.1

- Corrected missing translation (02/04/2026)

## v1.11.1

- Added javascript translations for javascript (02/04/2026)

## v1.11

- Removed height="auto" in Components calls (31/03/2026)
- Added title for sections (31/03/2026)

## v1.10.3

- Corrected findRandomProducts() (20/03/2026)

## v1.10.2

- Changed size for product/recommendation cards to have 2 aside in smartphone (20/03/2026)

## v1.10.1

- Added alphabetical order for categories in ProductCrudController (20/03/2026)
- Added recommended products on basket and product pages (20/03/2026)

## v1.10

- Added description to basket (20/03/2026)
- Changed style for Basket add/remove buttons (20/03/2026)
- Added categories select in shop (20/03/2026)
- Added category information and shop button to category display (20/03/2026)
- Renamed ProductSearch to ProductSearchComponent (20/03/2026)
- Conversion de ProductCategory to ManyToMany (20/03/2026) **Needs db migration** see [UPGRADE.md](UPGRADE.md)
- Split css in multiple files (20/03/2026)

## v1.9.6

- Added ProductSearch (19/03/2026)
- Added spanish translations (19/03/2026)

## v1.9.5

- Replaced linkToCrud by linkTo for EasyAdmin (17/03/2026)

## v1.9.4

- Corrected product queries (21/01/2026)

## v1.9.3

- Corrected missing ? in setter (18/01/2026)

## v1.9.2

- Corrected query for product (18/01/2026)

## v1.9.1

- Corrected ProductCrudController for availableAt property (18/01/2026)

## v1.9

- Replaced Vich\UploaderBundle\Mapping\Annotation by Vich\UploaderBundle\Mapping\Attribute (14/01/2026)
- Added product.availableAt property (18/01/2026)

## v1.8.4

- Corrected missing a in translation (17/11/2025)
- Corrected Lottery Draw Prize (17/11/2025)

## v1.8.3

- Corrected sitemap command for categories (20/10/2025)

## v1.8.2

- Removed category display on product page if null (10/10/2025)
- Corrected sitemap (10/10/2025)
- Removed text left for crowdfunding div (10/10/2025)

## v1.8.1

- Added ProductCategory to sitemaps (09/10/2025)

## v1.8

- Added ProductCategory entity to classify products (09/10/2025) **Needs migration**

## v1.7

- Replaced Symfony\Component\Routing\Annotation\Route by Symfony\Component\Routing\Attribute\Route (09/10/2025)

## v1.6.7

- Added description title to product (11/09/2025)
- Aligned to left the product description (11/09/2025)

## v1.6.6

- Removed paragraph tags linked to textarea in easyadmin (08/09/2025)

## v1.6.5

- Added style for crowdfunding div (08/09/2025)

## v1.6.4

- Added raw to button to allw html content (08/09/2025)

## v1.6.3

- Modified button under AmountAchieved when crowdfunding is not started or ended (01/08/2025)
- Added the possibility to upload a video for the lottery's draw (01/08/2025)

## v1.6.2

- Added Product:Card component (26/07/2025)
- Added Product:Button component (26/07/2025)

## v1.6.1

- Added a Slider component to be reused using product.medias (19/07/2025)

## v1.6

- Increased width for resized image and quality (19/07/2025)

## v1.5.1

- Added user's message to email and basket display (29/06/2025)
- Added possibility to flag a ProductItem as a service with no shipping (29/06/2025)

## v1.5

- Added message field on basket (29/06/2025)

## v1.4

- Added missing pagination for shop (26/06/2025)
- Modified default number of products to 12 (26/06/2025)

## v1.3.1

- Modified CTA button to allow other icon (09/06/2025)

## v1.3

- Added button cta for crowdfunding (06/06/2025)

## v1.2.1

- Corrected site name in Dashboard (29/05/2025)
- Added possibility to have html in counterpart (29/05/2025)
- Added help message at top form for Counterpart (29/05/2025)

## v1.2

- Corrected possibility when basket total is 0 (27/05/2025)

## v1.1.1

- Corrected label.add_news (27/05/2025)

## v1.1

- Corrected text alignment for lottery's prizes (27/05/2025)
- Emphasized limited quantity display (27/05/2025)
- Added possibility to add a YouTube video for crowdfunding (27/05/2025)

## v1.0.1

- Corrected english translations (23/05/2025)

## v1.0

- Added possibility of limitedQuantity = 0 (23/05/2025)
- Moved to production (23/05/2025)

## v0.31

- Added form to add News (23/05/2025)
- Added come back later + date on Counterpart button if crowdfunding not started (23/05/2025)

## v0.30.7

- Corrected DateImmutable (03/05/2025)
- Corrected email send for lottery tickets (03/05/2025)
- Corrected display for prizes (03/05/2025)

## v0.30.6.1

- Corrected shop.en.xlf (02/05/2025)

## v0.30.6

- Added styles (02/05/2025)
- Changed lottery prize descriptin to text (02/05/2025)
- Corrected persistence for Crowdfunding video (02/05/2025)

## v0.30.5

- Added number of the counterpart (02/05/2025)

## v0.30.4

- Modified files when crowdfunding has not started yet (02/05/2025)

## v0.30.3

- Replaced addPanel by addFieldset as deprecated (02/05/2025)

## v0.30.2

- Corrected limitedQUantity on productItems (26/04/2025)
- Added limitedQuantity on CrowdfundingCounterparts (26/04/2025)
- Added registration of orderedQuantity on productItems (26/04/2025)

## v0.30.1

- Replaced c975LEmail by c975LSite as c975LEmailBundle is abandonned (25/04/2025)

## v0.30

- Corrected generate lottery tickets for all avalailable lotteries of crowdfunding (25/04/2025)

## v0.29

- Added random suffle on lottery's tickets before draw (24/04/2025)
- Added missing translations (24/04/2025)
- Added limitedQuantity field to ProductItem (24/04/2025)
- Corrected limitedQuantity on ProductItem (24/04/2025)
- Added default position for Product/Crowdfunding (24/04/2025)
- Added missing fields for CrudController (24/04/2025)
- Added a Timezone "setter" in session to display hours correctly (24/04/2025)
- Added email send to lottery's winner (24/04/2025)
- Corrected quantity for lottery tickets when purchasing more thant one counterpart (24/04/2025)

## v0.28

- Removed toast (22/04/2025)
- Replaced Basket View button by including it in a fixed bottom navbar (22/04/2025)
- Added a test mode warning displayed message (22/04/2025)
- Corrected CrowdfundingContribor->name to allow null (23/04/2025)
- Updated README (23/04/2025)
- Added lottery system for Crowdfunding (23/04/2025)

## v0.27

- Added requiresShipping on Counterpart (20/04/2025)
- Corrected counterpart type to crowdfunding (20/04/2025)
- Added Item type in place of Digital (20/04/2025)
- Added default text for customizable parts (20/04/2025)
- Changed basket digital to contentFlag to allow more possibilities (20/04/2025)
- Finished system of crowdfunding contribution (20/04/2025)
- Added limitedQuantity on ProductItem (21/04/2025)
- Split shipped in two for tiems and counterparts (21/04/2025)

## v0.26

- Finished backoffice management of Crowdfunding (16/04/2025)
- Set CrowdfundingVideo as a collection (16/04/2025)

## v0.25

- Updated MediaDeleteCommand (16/04/2025)
- - Finished corrections for Shop items (16/04/2025)

## v0.24

- Corrected relations in entities (15/04/2025)
- Added relation Counterpart -> Contributor (15/04/2025)
- Renamed parts of ProductItems to items as strategy has changed to use a type of items (15/04/2025)

## v0.23

- Removed tables for media/file and made use of only one (11/04/2025)
- Added EasyAdmin CRUD controllers for crowdfundings (11/04/2025)
- Made use of a ShopMediaNamer (11/04/2025)
- Removed MediaTrait and use of VichUploader to resize image (11/04/2025)

## v0.22

- Added amount to CrowdfundingContributor (10/04/2025)
- Added CrowdfundingVideo (10/04/2025)
- Renamed CrowdfundingCounterpart quantityAvailable -> limitedQuantity (10/04/2025)
- Renamed CrowdfundingCounterpart quantityTaken -> orderedQuantity (10/04/2025)
- Finished frontend design for crowdfunding (10/04/2025)

## v0.21

- Added beginDate for Crowdfunding (09/04/2025)
- Added styles for Crowdfunding (09/04/2025)

## v0.20.2

- Corrected product.media that was not anymore an integer (06/04/2025)

## v0.20.1

- Corrected displayed size of download files (06/04/2025)
- Added basket icon where quantity is set (06/04/2025)
- Made use of format_currency (06/04/2025)
- Added quantity purchased in readonly basket (06/04/2025)

## v0.20

- Added Crowdfunding structure (05/04/2025)

## v0.19.4

- Corrected errors on namespace (05/04/2025)

## v0.19.3

- Added no products information (05/04/2025)
- Removed basket button if no products (05/04/2025)
- Added copyright (05/04/2025)

## v0.19.2

- Modified Delivery component to delete date as redundant with above information (05/04/2025)

## v0.19.1

- Updated Readme (05/04/2025)
- Added Track order steps even if not done to give information to user (05/04/2025)

## v0.19

- Corrected DownloadLinks (04/04/2025)
- Added desactivation of + button in basket when digital product as it has no sense (04/04/2025)
- Added join on queries to optimize them (04/04/2025)
- Enhanced basket AddRemoveButtons (04/04/2025)
- Added slug for productItem (04/04/2025)

## v0.18

- Added checkboxes for Terms of use/Terms of sales (03/04/2025)
- Moved back sending email to BasketService->paid() to avoid webhook not reached (03/04/2025)
- Added file size for download items, to indicate in emails (03/04/2025)
- Added ProductItem slug to downloaded filename to make it more clear (03/04/2025)
- Added file size to download email (03/04/2025)
- Added action to send email when physical products are sent (03/04/2025)
- Removed Basket paymentIdentifier (03/04/2025)
- Added Basket shipped/downloaded datetime (03/04/2025)
- Added securityToken for Basket, to be used in url, to avoid basket visibility with only its number (03/04/2025)

## v0.17.1

- Removed \ in Twig component as deprecated (02/04/2025)
- Added Email if Stripe error (02/04/2025)

## v0.17

- Corrected missing root link for sitemap (02/04/2025)
- Corrected Update of Product (02/04/2025)
- Removed use of StripeVersion (02/04/2025)
- Moved ApiKey to bundle.yaml (02/04/2025)
- Added Webhook support (02/04/2025)
- Removed unsued Payment fields (02/04/2025)
- Added Payment->stripeMethod (02/04/2025)
- Added link to Stripe payment from dashboard (02/04/2025)

## v0.16.1

- Added customer_email sent to StripeCheckout (02/04/2025)
- Made use of Messenger for confirmation order email (02/04/2025)
- Corrected basket not updated after payment and emails not sent (02/04/2025)
- Added forced download for productItemDownload (02/04/2025)

## v0.16

- Added intermediate step before basket validation (01/04/2025)
- Removed empty/validated basket templates and added functionnality in display one (01/04/2025)
- Added crontab to delete unvalidated baskets after 14 days (01/04/2025)
- Corrected user to null in Product (and sub entites) when adding to basket (01/04/2025)
- Added Command for creating sitemap (01/04/2025)

## v0.15.1

- Corrected display of product item file icon (29/03/2025)

## v0.15

- Corrected social for product (29/03/2025)
- Corrected differents texts + presentation (29/03/2025)
- Added esponse for item already downloaded (29/03/2025)
- Removed discouraged uses  (29/03/2025)
- Changed isNumeric to isDigital and transformed it to have 3 possible states (29/03/2025)
- Added Command to update position por Products, Items and Medias (29/03/2025)
- Added icon on ProductItemMedia (29/03/2025)

## v0.14.1

- Corrected template name (28/03/2025)

## v0.14

- Corrected email for download (28/03/2025)
- Added Command to remove download files (28/03/2025)

## v0.13

- Transformed productItem->file as a VichUploadable File (28/03/2025)
- Added file download after purchase (28/03/2025)

## v0.12

- Added image for ProductItem (27/03/2025)
- Added position for Product (27/03/2025)
- Added position for ProductItem (27/03/2025)
- Added position for ProductMedia (27/03/2025)
- Added display of id in shop management (27/03/2025)
- Added name field for basket (27/03/2025)
- Corrected display images on basket + email (27/03/2025)
- Added possibility to delete Basket/Payment for Admin (27/03/2025)
- Added links Payment <-> Basket (27/03/2025)
- Added buttons to filter Baskets (27/03/2025)
- Renamed ImageTrait to MediaTrait, more consistent (27/03/2025)

## v0.11

- Changed basket number format  (24/03/2025)
- Added Codacy corrections (24/03/2025)
- Added /shop to Basket Routes (24/03/2025)
- Added componenets to be overrided to allow perosnalisation (24/03/2025)

## v0.10

- Renamed table shop_media to shop_product_media (23/03/2025)
- Added ProductItem to manage the different versions of a product (23/03/2025)
- Renamed basket->products to productItems (23/03/2025)
- Added search bar component (23/03/2025)

## v0.9.1

- Corrections due to Codacy analysis (22/03/2025)

## v0.9

- Added relation to user in entities (22/03/2025)
- Suppressed payment->number as redundant (22/03/2025)
- Suppressed basket->identifier as not used because of number (22/03/2025)
- Changed format of basket number to be abale to have it in url but not predictable (22/03/2025)
- Move product added message below Basket (22/03/2025)
- Added +/- buttons (22/03/2025)

## v0.8.3

- Added a check to see if entity has already been processed for resizeImage() (09/03/2025)

## v0.8.2

- Added raw for product description (09/03/2025)

## v0.8.1

- Corrected call of getProductMediasNames() (09/03/2025)

## v0.8

- Added require of vich uploader to composer.json (03/03/2025)
- Renamed Media to PrductMedia (03/03/2025)
- Added ROLE_ADMIN requirement for shop management (03/03/2025)
- Added link to shop from dashboard (09/03/2025)
- Corrected component to display no product image in case of no image (09/03/2025)
- Added resize of photo for ProductMedia (09/03/2025)
- Added default image for ProductMedia (09/03/2025)

## v0.7

- Added "shop/" to product url  (01/03/2025)
- Corrected translation for country (01/03/2025)
- Changed Product field isNumeric to file (01/03/2025)
- Renamed table stripe_payment to shop_stripe_payment (01/03/2025)
- Suppressed description from Payment as not useful (01/03/2025)
- Renamed Payment->orderId to number as in basket (01/03/2025)
- Added management of shop via EasyAdmin (made use of word management instaed of admin as less used) (01/03/2025)

## v0.6.2

- Added link to product from its title (22/02/2025)

## v0.6.1

- Added shipping by default for basket creation (22/02/2025)
- Added currency in config (22/02/2025)

## v0.6

- Added basket number (13/12/2024)
- Added email sent to customer (13/12/2024)
- Added shipping (22/02/2025)
- Modified styles for products and basket (22/02/2025)

## v0.5.1

- Added translation domain by default (07/11/2024)
- Change "readonly" for product and based on basket.status, more accurate (07/11/2024)

## v0.5

- Finalized payment process (07/11/2024)
- Added empty and validated baskets (07/11/2024)

## v0.4

- Added FormType (06/11/2024)
- Added address fields as independant fields (06/11/2024)
- Added removal of a product (06/11/2024)
- Added translations (06/11/2024)
- Renamed Cart to Basket (06/11/2024)

## v0.3.1

- Corrected Cart entity (05/11/2024)

## v0.3

- Added Cart actions (05/11/2024)

## v0.2

- Revival of Bundle (26/09/2024)
- Added main Product (26/09/2024)
- Added Cart (26/09/2024)
- Added Media (26/09/2024)

## v0.1.2

- Corrected `composer.json` (05/12/2018)

## v0.1.1

- Removed required in composer.json (22/05/2018)
- Changed required versions in composer.json (04/12/2018)

## v0.1

- Creation of bundle (14/09/2017)
