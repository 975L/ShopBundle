<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Management\ProductStructuredDataHealthCheckProvider;
use c975L\ShopBundle\Service\ProductJsonLdClient;
use c975L\ShopBundle\Service\ProductServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the "product-json-ld" check reports of a sheet, from what the site actually serves rather than from what the builder would have written
class ProductStructuredDataHealthCheckProviderTest extends TestCase
{
    public function testTheKindIsTheOneTheDashboardAsksFor(): void
    {
        $this->assertSame('product-json-ld', $this->createProvider()->getKind());
    }

    // Nothing knows the site's own address before "site-url" is filled in, so there is no sheet to fetch
    public function testNothingIsCheckedWhileTheSiteHasNoUrl(): void
    {
        $this->assertSame([], $this->createProvider(siteUrl: '')->runChecks());
    }

    public function testTheSheetIsFetchedAtItsOwnPublicAddress(): void
    {
        $results = $this->createProvider(found: ['blocks' => 1, 'invalid' => 0, 'types' => ['Product']])->runChecks();

        $this->assertSame('https://example.com/shop/products/a-poster', $results[0]['url']);
        $this->assertSame('A poster', $results[0]['label']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('label.health_check_product_json_ld_ok', $results[0]['summary']);
    }

    // The trailing slash of a configured url is dropped rather than doubled into the sheet's address
    public function testATrailingSlashOnTheSiteUrlIsNotCarried(): void
    {
        $results = $this->createProvider(siteUrl: 'https://example.com/', found: ['blocks' => 1, 'invalid' => 0, 'types' => ['Product']])->runChecks();

        $this->assertSame('https://example.com/shop/products/a-poster', $results[0]['url']);
    }

    // A sheet nothing serves is not a defect of this bundle: a draft, a product deleted since, a route the site never enabled
    public function testASheetTheSiteDoesNotServeIsSkipped(): void
    {
        $results = $this->createProvider(served: false)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $results[0]['status']);
        $this->assertSame('label.health_check_product_not_served', $results[0]['summary']);
    }

    public function testASheetCarryingNoStructuredDataIsAnError(): void
    {
        $results = $this->createProvider(found: ['blocks' => 0, 'invalid' => 0, 'types' => []])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('label.health_check_product_json_ld_missing', $results[0]['summary']);
    }

    // Markup that does not parse is reported before what it was missing: it is the defect nobody reading the page can see
    public function testABlockThatDoesNotParseIsAnErrorOfItsOwn(): void
    {
        $results = $this->createProvider(found: ['blocks' => 2, 'invalid' => 1, 'types' => ['Product']])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('label.health_check_product_json_ld_invalid', $results[0]['summary']);
    }

    // Served, parsing, and still saying nothing about what is sold: a warning rather than an error
    public function testASheetCarryingOnlyTheBreadcrumbIsAWarning(): void
    {
        $results = $this->createProvider(found: ['blocks' => 1, 'invalid' => 0, 'types' => ['BreadcrumbList']])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame('label.health_check_product_json_ld_no_product', $results[0]['summary']);
    }

    // One sheet failing to answer must not take the whole run down with it
    public function testACallThatThrowsIsReportedAsAnErrorOnThatSheetAlone(): void
    {
        $results = $this->createProvider(throwing: 'Connection timed out')->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('label.health_check_product_json_ld_call_failed', $results[0]['summary']);
        $this->assertSame(['error' => 'Connection timed out'], $results[0]['details']);
    }

    // The row is read from the dashboard, where the whole point is to go and fix the sheet it names
    public function testEveryRowCarriesTheEditUrlOfItsProduct(): void
    {
        $results = $this->createProvider(found: ['blocks' => 1, 'invalid' => 0, 'types' => ['Product']])->runChecks();

        $this->assertSame('/admin/product/7/edit', $results[0]['editUrl']);
    }

    private function createProvider(
        string $siteUrl = 'https://example.com',
        bool $served = true,
        ?array $found = null,
        ?string $throwing = null,
    ): ProductStructuredDataHealthCheckProvider {
        $productService = $this->createStub(ProductServiceInterface::class);
        $productService->method('findAll')->willReturn([$this->createProduct()]);

        $jsonLdClient = $this->createStub(ProductJsonLdClient::class);
        if (null !== $throwing) {
            $jsonLdClient->method('readStructuredData')->willThrowException(new \RuntimeException($throwing));
        } else {
            $jsonLdClient->method('readStructuredData')->willReturn($found ?? ['blocks' => 1, 'invalid' => 0, 'types' => ['Product']]);
        }

        $urlStatusChecker = $this->createStub(UrlStatusChecker::class);
        $urlStatusChecker->method('exists')->willReturn($served);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProductStructuredDataHealthCheckProvider(
            $productService,
            $jsonLdClient,
            $urlStatusChecker,
            $configService,
            $this->createAdminUrlGenerator(),
            $translator,
        );
    }

    private function createProduct(): Product
    {
        $product = new Product()->setTitle('A poster')->setSlug('a-poster');
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 7);

        return $product;
    }

    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $entityId = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnCallback(function (mixed $value) use ($generator, &$entityId) {
            $entityId = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use (&$entityId): string {
            return '/admin/product/' . $entityId . '/edit';
        });

        return $generator;
    }
}
