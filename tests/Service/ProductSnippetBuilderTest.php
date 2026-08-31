<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PaymentBundle\Service\ShippingRateResolverInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Service\ProductSnippetBuilder;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\RatingSnippetBuilder;
use PHPUnit\Framework\TestCase;

// The schema.org graph a product sheet publishes - the offers node above all, which is what a rich result shows and what no other bundle of the ecosystem emits
class ProductSnippetBuilderTest extends TestCase
{
    private ProductSnippetBuilder $builder;

    protected function setUp(): void
    {
        // Nothing configured: shipping and returns are PaymentBundle's keys, and a shop that has not filled them in publishes neither
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn(null);

        $this->builder = new ProductSnippetBuilder($configService, new ProductStateService(), $this->ratingSnippetBuilder(), $this->shippingRateResolver(null));
    }

    // The same builder against a shop that did fill those keys in
    private function builder(array $config, array $aggregate = ['average' => 0.0, 'count' => 0], ?int $shipping = null): ProductSnippetBuilder
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $config[$key] ?? null);

        return new ProductSnippetBuilder($configService, new ProductStateService(), $this->ratingSnippetBuilder($aggregate), $this->shippingRateResolver($shipping));
    }

    // The grid PaymentBundle prices a parcel on, which the graph states for one article's own weight
    private function shippingRateResolver(?int $price): ShippingRateResolverInterface
    {
        $resolver = $this->createStub(ShippingRateResolverInterface::class);
        $resolver->method('resolve')->willReturn($price);

        return $resolver;
    }

    public function testAProductWithoutTitlePublishesNothing(): void
    {
        $this->assertSame([], $this->builder->buildProduct(new Product()));
        $this->assertSame('', $this->builder->buildJson($this->builder->buildProduct(new Product())));
    }

    public function testAFilledProductCarriesItsOwnFields(): void
    {
        $snippet = $this->builder->buildProduct($this->product(), 'https://example.org/affiche.webp', 'https://example.org/shop/products/affiche');

        $this->assertSame('https://schema.org', $snippet['@context']);
        $this->assertSame('Product', $snippet['@type']);
        $this->assertSame('Affiche', $snippet['name']);
        $this->assertSame('https://example.org/shop/products/affiche', $snippet['url']);
        $this->assertSame('https://example.org/affiche.webp', $snippet['image']);
        $this->assertSame('affiche', $snippet['sku']);
        $this->assertSame('Affiches', $snippet['category']);
    }

    // The description is written in a rich text editor, and a graph carries the words only
    public function testTheDescriptionReachesTheGraphWithoutItsMarkup(): void
    {
        $product = $this->product()->setDescription("<p>Une affiche\n  en <strong>A2</strong>&nbsp;!</p>");

        $this->assertSame('Une affiche en A2 !', $this->builder->buildProduct($product)['description']);
    }

    // Prices are stored in cents and charged in units, and a currency code is uppercase in ISO 4217
    public function testAnOfferStatesThePriceAsItIsCharged(): void
    {
        $offer = $this->builder->buildProduct($this->product(), null, 'https://example.org/shop/products/affiche')['offers'][0];

        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame('12.50', $offer['price']);
        $this->assertSame('EUR', $offer['priceCurrency']);
        $this->assertSame('a2', $offer['sku']);
        $this->assertSame('https://example.org/shop/products/affiche#a2', $offer['url']);
    }

    // The item's card shows a description of its own, and the graph carries it as the words only
    public function testAnOfferCarriesTheItemsOwnDescription(): void
    {
        $product = $this->product();
        $product->getItems()->first()->setDescription('<p>Format <strong>A2</strong></p>');

        $this->assertSame('Format A2', $this->builder->buildProduct($product)['offers'][0]['description']);
    }

    // The item's own picture, resolved by the caller and keyed by the item's slug
    public function testAnOfferCarriesTheItemsOwnImage(): void
    {
        $offers = $this->builder->buildProduct($this->product(), null, null, ['a2' => 'https://example.org/a2.webp'])['offers'];

        $this->assertSame('https://example.org/a2.webp', $offers[0]['image']);
    }

    // An item with no picture of its own never borrows the product's
    public function testAnItemWithoutAPictureCarriesNoImage(): void
    {
        $product = $this->product()->addItem($this->item('a3', 800));
        $offers = $this->builder->buildProduct($product, 'https://example.org/affiche.webp', null, ['a2' => 'https://example.org/a2.webp'])['offers'];

        $this->assertArrayNotHasKey('image', $offers[1]);
    }

    // The column stores a token, the graph publishes the schema.org url it stands for
    public function testAnItemsConditionIsPublishedAsItsSchemaOrgUrl(): void
    {
        $product = $this->product();
        $product->getItems()->first()->setItemCondition('refurbished');

        $this->assertSame('https://schema.org/RefurbishedCondition', $this->builder->buildProduct($product)['offers'][0]['itemCondition']);
    }

    // Not stating the condition is a state of its own: claiming "new" over a second-hand item is worse than saying nothing
    public function testAnItemWhoseConditionIsNotStatedPublishesNone(): void
    {
        $this->assertArrayNotHasKey('itemCondition', $this->builder->buildProduct($this->product())['offers'][0]);
    }

    // One offer per item rather than an aggregate: two items of one product are free to be priced in two currencies
    public function testEachItemBecomesAnOfferOfItsOwn(): void
    {
        $product = $this->product()->addItem($this->item('a3', 800));

        $this->assertCount(2, $this->builder->buildProduct($product)['offers']);
    }

    // An item set aside has left the sheet, so it publishes no offer for something the shop no longer sells
    public function testAHiddenItemPublishesNoOffer(): void
    {
        $product = $this->product()->addItem($this->item('a3', 800)->setHidden(true));

        $offers = $this->builder->buildProduct($product)['offers'];

        $this->assertCount(1, $offers);
        $this->assertSame('A2', $offers[0]['name']);
    }

    // A product whose every item is set aside publishes no offers node at all rather than an empty one
    public function testAProductHidingEveryItemPublishesNoOffersNode(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($this->item('a2', 1250)->setHidden(true));

        $this->assertArrayNotHasKey('offers', $this->builder->buildProduct($product));
    }

    // The very rules AddButton.html.twig disables its button on, so the graph never says "in stock" over a button that cannot be clicked
    public function testAnItemDeclaredAtZeroIsOutOfStock(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($this->item('a2', 1250)->setLimitedQuantity(0));

        $this->assertSame('https://schema.org/OutOfStock', $this->builder->buildProduct($product)['offers'][0]['availability']);
    }

    public function testAnItemWhoseLimitIsReachedIsSoldOut(): void
    {
        $item = $this->item('a2', 1250)->setLimitedQuantity(10)->setOrderedQuantity(10);
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($item);

        $this->assertSame('https://schema.org/SoldOut', $this->builder->buildProduct($product)['offers'][0]['availability']);
    }

    public function testAProductNotReleasedYetIsOnPreOrder(): void
    {
        $product = $this->product()->setAvailableAt(new \DateTime('+1 month'));

        $this->assertSame('https://schema.org/PreOrder', $this->builder->buildProduct($product)['offers'][0]['availability']);
    }

    public function testAnAvailableItemIsInStock(): void
    {
        $this->assertSame('https://schema.org/InStock', $this->builder->buildProduct($this->product())['offers'][0]['availability']);
    }

    // An unfilled field never reaches the graph as a blank property
    public function testWhatIsLeftEmptyIsLeftOut(): void
    {
        $snippet = $this->builder->buildProduct(new Product()->setTitle('Affiche')->setSlug('affiche'));

        $this->assertArrayNotHasKey('image', $snippet);
        $this->assertArrayNotHasKey('description', $snippet);
        $this->assertArrayNotHasKey('category', $snippet);
        $this->assertArrayNotHasKey('offers', $snippet);
    }

    // The same rule inside an offer: an item left without a description publishes none
    public function testAnItemLeftWithoutADescriptionPublishesNone(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($this->item('a2', 1250)->setDescription(''));

        $this->assertArrayNotHasKey('description', $this->builder->buildProduct($product)['offers'][0]);
    }

    // What the Shipping component of the sheet states, said again where a search engine reads it - priced on the grid for this article's own weight, and naming the country it is priced for
    public function testAnOfferCarriesTheRateTheGridPricesItsWeightAt(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 490);
        $product = $this->product();
        $product->getItems()->first()->setWeight(850);

        $shipping = $builder->buildProduct($product)['offers'][0]['shippingDetails'];

        $this->assertSame('OfferShippingDetails', $shipping['@type']);
        $this->assertSame('4.90', $shipping['shippingRate']['value']);
        $this->assertSame('EUR', $shipping['shippingRate']['currency']);
        $this->assertSame('FR', $shipping['shippingDestination']['addressCountry']);
    }

    // The grid answers per weight and per zone: an article nobody weighed has no rate to publish, and one of its tiers stated as if it covered every parcel would be a guess
    public function testAnUnweighedArticlePublishesNoShippingRate(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 490);

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($this->product())['offers'][0]);
    }

    // A rate published without saying where it posts to is a rate for nowhere
    public function testAShopNamingNoDefaultCountryPublishesNoShippingRate(): void
    {
        $builder = $this->builder(['shop-currency' => 'eur'], shipping: 490);
        $product = $this->product();
        $product->getItems()->first()->setWeight(850);

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($product)['offers'][0]);
    }

    // A grid saying nothing about that parcel declares nothing, rather than a zero rate which reads as free shipping
    public function testAGridSayingNothingPublishesNoShippingRate(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: null);
        $product = $this->product();
        $product->getItems()->first()->setWeight(850);

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($product)['offers'][0]);
    }

    // The rule telling a shipped item from the rest is ProductBasketItemProvider's: a service is rendered, never posted
    public function testAServiceIsNotShipped(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 490);
        $product = new Product()->setTitle('Atelier')->setSlug('atelier')->addItem($this->item('seance', 5000)->setService(true)->setWeight(850));

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($product)['offers'][0]);
    }

    // A file is downloaded rather than posted, but the empty placeholder ProductItemListener attaches to every new item is not one: what tells them apart is its name, here as in ProductBasketItemProvider
    public function testAnItemCarryingAnEmptyFilePlaceholderIsStillShipped(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 490);
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($this->item('a2', 1250)->setFile(new ProductItemFile())->setWeight(850));

        $this->assertSame('OfferShippingDetails', $builder->buildProduct($product)['offers'][0]['shippingDetails']['@type']);
    }

    // A named file is downloaded rather than posted, and the grid pricing its weight for a named country changes nothing to that
    public function testADownloadedItemIsNotShipped(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 490);
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->addItem($this->item('a2', 1250)->setFile(new ProductItemFile()->setName('affiche.pdf'))->setWeight(850));

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($product)['offers'][0]);
    }

    // A shop that charges nothing has nothing to declare here rather than a zero rate, which reads as free shipping
    public function testAShopChargingNothingForShippingDeclaresNoRate(): void
    {
        $builder = $this->builder(['shop-shipping-country' => 'FR', 'shop-currency' => 'eur'], shipping: 0);
        $product = $this->product();
        $product->getItems()->first()->setWeight(850);

        $this->assertArrayNotHasKey('shippingDetails', $builder->buildProduct($product)['offers'][0]);
    }

    // The link and nothing else: no column of this ecosystem holds the return window, and a guessed one is a promise the shop never made
    public function testAnOfferPointsAtTheReturnPolicyWhenTheShopPublishedOne(): void
    {
        $builder = $this->builder(['url-terms-of-sales' => 'https://example.org/terms-of-sales']);
        $policy = $builder->buildProduct($this->product())['offers'][0]['hasMerchantReturnPolicy'];

        $this->assertSame('MerchantReturnPolicy', $policy['@type']);
        $this->assertSame('https://example.org/terms-of-sales', $policy['merchantReturnLink']);
        $this->assertArrayNotHasKey('merchantReturnDays', $policy);
    }

    public function testAShopWithoutTermsOfSalesPublishesNoReturnPolicy(): void
    {
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $this->builder->buildProduct($this->product())['offers'][0]);
    }

    // A "</script>" typed into any field must not close the tag the graph is served in
    public function testTheEncodedGraphCannotCloseItsOwnScriptTag(): void
    {
        $product = $this->product()->setDescription('</script><script>alert(1)</script>');

        $this->assertStringNotContainsString('</script>', $this->builder->buildJson($this->builder->buildProduct($product)));
    }

    public function testAProductNamingItsMakerPublishesABrandNode(): void
    {
        $snippet = $this->builder->buildProduct($this->product()->setBrand('Éditions Lolant'));

        $this->assertSame(['@type' => 'Brand', 'name' => 'Éditions Lolant'], $snippet['brand']);
    }

    // A product made in-house names no brand, and the node is left out rather than emitted empty
    public function testAProductWithoutABrandPublishesNoBrandNode(): void
    {
        $this->assertArrayNotHasKey('brand', $this->builder->buildProduct($this->product()));
    }

    public function testAnOfferCarriesTheShopsOwnReferenceWhenItStatesOne(): void
    {
        $product = $this->product();
        $product->getItems()[0]->setSku('AFF-A2-001')->setGtin('3760123456789');
        $offer = $this->builder->buildProduct($product)['offers'][0];

        $this->assertSame('AFF-A2-001', $offer['sku']);
        $this->assertSame('3760123456789', $offer['gtin']);
    }

    // The slug is what every offer of this graph carried before the column existed, and what it falls back on
    public function testAnOfferWithoutAReferenceFallsBackOnItsSlug(): void
    {
        $offer = $this->builder->buildProduct($this->product())['offers'][0];

        $this->assertSame('a2', $offer['sku']);
        $this->assertArrayNotHasKey('gtin', $offer);
    }

    public function testAnOfferOnSalePublishesItsListPrice(): void
    {
        $product = $this->product();
        $product->getItems()[0]->setPriceBefore(2000);
        $offer = $this->builder->buildProduct($product)['offers'][0];

        $this->assertSame([
            '@type' => 'UnitPriceSpecification',
            'priceType' => 'https://schema.org/ListPrice',
            'price' => '20.00',
            'priceCurrency' => 'EUR',
        ], $offer['priceSpecification']);
    }

    // The graph holds to the very rule the struck-through price is printed on: a previous price below the current one is not an offer, here either
    public function testAnOfferWhosePreviousPriceIsNotAboveItPublishesNoListPrice(): void
    {
        $product = $this->product();
        $product->getItems()[0]->setPriceBefore(900);

        $this->assertArrayNotHasKey('priceSpecification', $this->builder->buildProduct($product)['offers'][0]);
    }

    public function testABreadcrumbNumbersItsLevelsInReadingOrder(): void
    {
        $breadcrumb = $this->builder->buildBreadcrumb([
            ['name' => 'Boutique', 'url' => 'https://example.org/shop'],
            ['name' => 'Affiches', 'url' => 'https://example.org/shop/category/affiches'],
            ['name' => 'Affiche', 'url' => 'https://example.org/shop/products/affiche'],
        ]);

        $this->assertSame('BreadcrumbList', $breadcrumb['@type']);
        $this->assertCount(3, $breadcrumb['itemListElement']);
        $this->assertSame([1, 2, 3], array_column($breadcrumb['itemListElement'], 'position'));
        $this->assertSame('Affiches', $breadcrumb['itemListElement'][1]['name']);
    }

    // A level with nothing to show is dropped rather than numbered, which would leave a gap in the positions
    public function testABreadcrumbDropsALevelItCannotName(): void
    {
        $breadcrumb = $this->builder->buildBreadcrumb([
            ['name' => 'Boutique', 'url' => 'https://example.org/shop'],
            ['name' => '', 'url' => 'https://example.org/shop/category/affiches'],
            ['name' => 'Affiche', 'url' => 'https://example.org/shop/products/affiche'],
        ]);

        $this->assertSame([1, 2], array_column($breadcrumb['itemListElement'], 'position'));
    }

    // One level is the page itself: a trail leading nowhere says nothing a search engine does not already read in the url
    public function testATrailOfOneLevelPublishesNothing(): void
    {
        $this->assertSame([], $this->builder->buildBreadcrumb([['name' => 'Boutique', 'url' => 'https://example.org/shop']]));
        $this->assertSame([], $this->builder->buildBreadcrumb([]));
    }

    private function product(): Product
    {
        return new Product()
            ->setTitle('Affiche')
            ->setSlug('affiche')
            ->setDescription('Une affiche')
            ->addCategory(new ProductCategory()->setName('Affiches')->setSlug('affiches'))
            ->addItem($this->item('a2', 1250));
    }

    private function item(string $slug, int $price): ProductItem
    {
        return new ProductItem()
            ->setTitle(strtoupper($slug))
            ->setSlug($slug)
            ->setDescription('Format ' . strtoupper($slug))
            ->setPrice($price)
            ->setCurrency('eur')
            // Null is what an item with no stock limit carries; the column defaults to 0, which the shop reads as "unavailable"
            ->setLimitedQuantity(null);
    }

    // The votes UiBundle holds, as this bundle reads them: the builder under test only ever asks for the node, so the tally is stubbed rather than queried
    private function ratingSnippetBuilder(array $aggregate = ['average' => 0.0, 'count' => 0]): RatingSnippetBuilder
    {
        $ratingService = $this->createStub(RatingService::class);
        $ratingService->method('getAggregate')->willReturn($aggregate);
        $ratingService->method('getScale')->willReturn(5);

        return new RatingSnippetBuilder($ratingService);
    }

    // The sheet showing no stars publishes none, whatever has been voted
    public function testASheetWithoutItsRatingWidgetPublishesNoAggregateRating(): void
    {
        $builder = $this->builder([], ['average' => 4.5, 'count' => 12]);

        $this->assertArrayNotHasKey('aggregateRating', $builder->buildProduct($this->product()));
    }

    // The very tally the widget prints, nested in the product rather than published beside it
    public function testASheetShowingItsRatingCarriesTheVotesInItsProductNode(): void
    {
        $builder = $this->builder([], ['average' => 4.5, 'count' => 12]);
        $snippet = $builder->buildProduct($this->product(), null, null, [], true);

        $this->assertSame([
            '@type' => 'AggregateRating',
            'ratingValue' => '4.5',
            'ratingCount' => 12,
            'bestRating' => 5,
            'worstRating' => 1,
        ], $snippet['aggregateRating']);
    }

    // Nobody voted: the node is dropped rather than published at zero, which is what invalidates the whole rich result
    public function testAProductNobodyVotedOnCarriesNoAggregateRating(): void
    {
        $this->assertArrayNotHasKey('aggregateRating', $this->builder->buildProduct($this->product(), null, null, [], true));
    }

    public function testAListingPublishesItsCardsAsAnItemList(): void
    {
        $snippet = $this->builder->buildItemList([
            ['name' => 'Affiche', 'url' => 'https://example.org/shop/products/affiche'],
            ['name' => 'Carte', 'url' => 'https://example.org/shop/products/carte'],
        ]);

        $this->assertSame('ItemList', $snippet['@type']);
        $this->assertSame(2, $snippet['numberOfItems']);
        $this->assertSame(1, $snippet['itemListElement'][0]['position']);
        $this->assertSame('https://example.org/shop/products/carte', $snippet['itemListElement'][1]['url']);
    }

    // The second page numbers its cards from where the first stopped
    public function testASecondPageNumbersItsCardsFromTheOffset(): void
    {
        $snippet = $this->builder->buildItemList([['name' => 'Carte', 'url' => 'https://example.org/shop/products/carte']], 12);

        $this->assertSame(13, $snippet['itemListElement'][0]['position']);
    }

    // A card pointing nowhere is dropped rather than numbered, and a list left with nothing publishes nothing
    public function testAnEmptyListingPublishesNothing(): void
    {
        $this->assertSame([], $this->builder->buildItemList([['name' => 'Affiche', 'url' => '']]));
        $this->assertSame('', $this->builder->buildJson($this->builder->buildItemList([])));
    }
}
