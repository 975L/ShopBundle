<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Template;

use PHPUnit\Framework\TestCase;

// What the sheet asks UiBundle's rating widget for, and what a deletion owes it back - a rating names its owner (see c975L\UiBundle\Entity\Rating) rather than relating to it, so nothing cascades it. Nothing renders or runs here, so both contracts are read where they are written
class ProductRatingTest extends TestCase
{
    private const string SHEET = 'templates/product/display.html.twig';
    private const string CRUD = 'src/Controller/Management/ProductCrudController.php';

    // The thing rated is named the way Rating stores it, and the id is the product's own
    public function testTheSheetNamesTheProductItRates(): void
    {
        $this->assertStringContainsString(
            '<twig:c975LUi:Rating:Rating ownerType="shop_product" ownerId="{{ product.id }}"/>',
            $this->read(self::SHEET)
        );
    }

    // No scale and no icon of its own: a shop rates on whatever the site set for the whole of it, unlike the gallery's one-heart like
    public function testTheSheetLeavesTheShapeToTheSiteSettings(): void
    {
        $sheet = $this->read(self::SHEET);

        $this->assertStringNotContainsString('Rating:Rating ownerType="shop_product" ownerId="{{ product.id }}" scale=', $sheet);
        $this->assertStringNotContainsString('Rating:Rating ownerType="shop_product" ownerId="{{ product.id }}" icon=', $sheet);
    }

    // On unless the site turned it off: a sheet says what its customers think of the product, and the setting is there for the shop that would rather it did not
    public function testTheWidgetOnlyShowsWhereTheSiteLeftItOn(): void
    {
        $sheet = $this->read(self::SHEET);

        $this->assertStringContainsString("{% set showsRating = config('shop-rating')|to_bool %}", $sheet);
        $this->assertStringContainsString('{% if showsRating %}', $sheet);
    }

    // The graph published at the bottom of the sheet reads the very condition the widget above it does: a rich result showing stars nobody finds on the page is worse than one showing none
    public function testTheGraphCarriesTheVotesOnlyWhereTheWidgetShowsThem(): void
    {
        $this->assertStringContainsString("product_json_ld(product, ogImage|default(null), url('product_display', {'slug': product.slug}), showsRating)", $this->read(self::SHEET));
    }

    public function testTheSettingIsDeclaredAndOnByDefault(): void
    {
        $configs = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/config/configs.json'), true, 512, \JSON_THROW_ON_ERROR);

        $entry = array_values(array_filter($configs, static fn (array $config): bool => 'shop-rating' === $config['slug']));

        $this->assertCount(1, $entry, 'No config declares the slug "shop-rating"');
        $this->assertSame('bool', $entry[0]['kind']);
        $this->assertSame('true', $entry[0]['value']);
    }

    // The votes, the wishlists and the reviews are dropped where the product is removed for good, and nowhere else: one sitting in the trash can still be restored, and it must find all three where it left them
    public function testTheRatingsAreDroppedOnThePermanentDeletionAlone(): void
    {
        $crud = $this->read(self::CRUD);

        // Three times and no more: the ratings, the favourites and the reviews, all three hanging off "shop_product" + id and cascaded by nothing
        $this->assertSame(3, substr_count($crud, "deleteForOwner('shop_product'"));

        $permanent = substr($crud, strpos($crud, 'public function deletePermanently('));
        $body = substr($permanent, 0, strpos($permanent, 'private function'));

        $this->assertSame(3, substr_count($body, "deleteForOwner('shop_product'"));
        $this->assertStringContainsString('$ratingRepository->deleteForOwner', $body);
        $this->assertStringContainsString('$favoriteRepository->deleteForOwner', $body);
        $this->assertStringContainsString('$reviewRepository->deleteForOwner', $body);
    }

    // The sheet gathers what buyers wrote about this very product, before the recommendations that send the reader elsewhere
    public function testTheSheetDrawsTheReviewsOfTheProduct(): void
    {
        $sheet = $this->read(self::SHEET);

        $this->assertStringContainsString("ui_reviews_section('shop_product', product.id)", $sheet);
        $this->assertLessThan(
            strpos($sheet, 'Product:Recommendations'),
            strpos($sheet, 'ui_reviews_section('),
            'The recommendations send the reader to another product, so what is said about this one comes first'
        );
    }

    // A site that collects no reviews draws no section and asks no query for it - the switch is read by the function itself, which answers an empty string, so the sheet carries no condition of its own
    public function testTheReviewsSectionIsGatedOnTheFeatureSwitch(): void
    {
        $this->assertStringNotContainsString('{% if ui_reviews_enabled() %}', $this->read(self::SHEET));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
