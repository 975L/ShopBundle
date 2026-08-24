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
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Management\ShopIntegrityHealthCheckProvider;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a shop cannot see about itself: a file paid for and never sent, a file on sale that left the server, an article sold past what is left of it
class ShopIntegrityHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/shop-integrity-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/private/medias/shop/items');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // "Checked, nothing found" and not silence - silence is what a check that never ran looks like
    public function testASoundShopReportsEveryCheckAsGreen(): void
    {
        $rows = $this->provider()->runChecks();

        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertSame(HealthCheckResult::STATUS_OK, $row['status'], $row['url']);
        }
    }

    // Paid, confirmed, and nothing was ever copied nor sent: the buyer has an order and no file, and only they know it
    public function testAnOrderWhoseFilesWereNeverCopiedIsReported(): void
    {
        $row = $this->row($this->provider(['orders' => [$this->order('2026-000042')], 'fileItems' => [12 => ['title' => 'A story', 'file' => 'story.pdf', 'size' => 10]]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_UNDELIVERED_DOWNLOADS);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $row['status']);
        $this->assertSame('2026-000042', $row['details']['offenders'][0]['label']);
    }

    public function testAnOrderWhoseCopiesWereMadeIsNotReported(): void
    {
        $row = $this->row($this->provider([
            'orders' => [$this->order('2026-000043')],
            'fileItems' => [12 => ['title' => 'A story', 'file' => 'story.pdf', 'size' => 10]],
            'delivered' => [42],
        ])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_UNDELIVERED_DOWNLOADS);

        $this->assertSame(HealthCheckResult::STATUS_OK, $row['status']);
    }

    // The copies are made by a message handler: an order settled moments before the run is being delivered, not left undelivered
    public function testAnOrderPaidMomentsAgoIsLeftAlone(): void
    {
        $order = $this->order('2026-000044')->setModification(new \DateTime());

        $row = $this->row($this->provider(['orders' => [$order], 'fileItems' => [12 => ['title' => 'A story', 'file' => 'story.pdf', 'size' => 10]]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_UNDELIVERED_DOWNLOADS);

        $this->assertSame(HealthCheckResult::STATUS_OK, $row['status']);
    }

    // The sheet still offers it and the checkout still takes the money: the delivery skips the item rather than failing
    public function testAFileMissingFromTheServerIsReported(): void
    {
        $row = $this->row($this->provider(['items' => [$this->item(file: 'medias/shop/items/gone.pdf')]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_MISSING_FILES);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $row['status']);
        $this->assertSame('medias/shop/items/gone.pdf', $row['details']['offenders'][0]['info']);
        $this->assertSame('A book / An item', $row['details']['offenders'][0]['label']);
    }

    public function testAFileThatIsWhereItIsReadFromIsNotReported(): void
    {
        touch($this->projectDir . '/private/medias/shop/items/there.pdf');

        $row = $this->row($this->provider(['items' => [$this->item(file: 'medias/shop/items/there.pdf')]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_MISSING_FILES);

        $this->assertSame(HealthCheckResult::STATUS_OK, $row['status']);
    }

    public function testAnArticleSoldPastItsStockIsReported(): void
    {
        $row = $this->row($this->provider(['items' => [$this->item()->setLimitedQuantity(3)->setOrderedQuantity(5)]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_OVERSOLD_ITEMS);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $row['status']);
        $this->assertSame('5 ordered of 3', $row['details']['offenders'][0]['info']);
    }

    public function testAnArticleWithinItsStockIsNotReported(): void
    {
        $row = $this->row($this->provider(['items' => [$this->item()->setLimitedQuantity(3)->setOrderedQuantity(3)]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_OVERSOLD_ITEMS);

        $this->assertSame(HealthCheckResult::STATUS_OK, $row['status']);
    }

    // A story given away is a shop's own decision - what a warning says is "look at this", never "this is broken"
    public function testAnArticleOnSaleForNothingIsAWarning(): void
    {
        $row = $this->row($this->provider(['items' => [$this->item()->setPrice(0), $this->item(), $this->item()]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_FREE_ITEMS);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $row['status']);
        $this->assertSame('price 0', $row['details']['offenders'][0]['info']);
    }

    // A catalogue giving away more than it sells is a site's own model - a check turning that orange every week is one that gets switched off
    public function testACatalogueGivingMoreThanItSellsIsSkipped(): void
    {
        $row = $this->row($this->provider(['items' => [$this->item()->setPrice(0), $this->item()->setPrice(0), $this->item()]])->runChecks(), ShopIntegrityHealthCheckProvider::ROW_FREE_ITEMS);

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $row['status']);
        $this->assertSame('label.health_check_shop_free_items_skipped', $row['summary']);
    }

    // HealthCheckRunner drops every row of a provider that throws, and no rows at all reads as "nothing to report"
    public function testAFailingCheckReportsItselfWithoutTakingTheOthersDown(): void
    {
        $itemRepository = $this->createStub(ProductItemRepository::class);
        $itemRepository->method('findSellable')->willThrowException(new \RuntimeException('Table gone'));

        $rows = new ShopIntegrityHealthCheckProvider(
            $this->basketRepository([]),
            $itemRepository,
            $this->downloadRepository([]),
            $this->downloadService([]),
            $this->siteUrlResolver('https://example.com/'),
            $this->translator(),
            $this->projectDir,
        )->runChecks();

        $this->assertCount(4, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $this->row($rows, ShopIntegrityHealthCheckProvider::ROW_MISSING_FILES)['status']);
        $this->assertSame('Table gone', $this->row($rows, ShopIntegrityHealthCheckProvider::ROW_MISSING_FILES)['details']['error']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $this->row($rows, ShopIntegrityHealthCheckProvider::ROW_UNDELIVERED_DOWNLOADS)['status']);
    }

    // Same guard as every site-wide check: without a site url there is nothing to key a row on
    public function testNothingIsCheckedWithoutASiteUrl(): void
    {
        $this->assertSame([], $this->provider([], null)->runChecks());
    }

    /**
     * @param array{orders?: list<Basket>, items?: list<ProductItem>, fileItems?: array<int, array<string, mixed>>, delivered?: list<int>} $found
     */
    private function provider(array $found = [], ?string $siteRoot = 'https://example.com/'): ShopIntegrityHealthCheckProvider
    {
        return new ShopIntegrityHealthCheckProvider(
            $this->basketRepository($found['orders'] ?? []),
            $this->itemRepository($found['items'] ?? []),
            $this->downloadRepository($found['delivered'] ?? []),
            $this->downloadService($found['fileItems'] ?? []),
            $this->siteUrlResolver($siteRoot),
            $this->translator(),
            $this->projectDir,
        );
    }

    private function basketRepository(array $orders): BasketRepository
    {
        $repository = $this->createStub(BasketRepository::class);
        $repository->method('findOrdersSince')->willReturn($orders);

        return $repository;
    }

    private function itemRepository(array $items): ProductItemRepository
    {
        $repository = $this->createStub(ProductItemRepository::class);
        $repository->method('findSellable')->willReturn($items);

        return $repository;
    }

    private function downloadRepository(array $delivered): ProductItemDownloadRepository
    {
        $repository = $this->createStub(ProductItemDownloadRepository::class);
        $repository->method('findDeliveredBasketIds')->willReturn($delivered);

        return $repository;
    }

    private function downloadService(array $fileItems): ProductItemDownloadServiceInterface
    {
        $service = $this->createStub(ProductItemDownloadServiceInterface::class);
        $service->method('getFileItems')->willReturn($fileItems);

        return $service;
    }

    private function siteUrlResolver(?string $siteRoot): SiteUrlResolver
    {
        $resolver = $this->createStub(SiteUrlResolver::class);
        $resolver->method('siteRoot')->willReturn($siteRoot);

        return $resolver;
    }

    // The translation ids themselves, so the assertions read what the provider asked for rather than what a catalog answers
    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []) => $id . ($parameters['%message%'] ?? ''));

        return $translator;
    }

    // Settled yesterday, so the delivery has had all the time it needs
    private function order(string $number): Basket
    {
        $basket = new Basket()
            ->setNumber($number)
            ->setStatus('paid')
            ->setEmail('buyer@example.com')
            ->setItems([])
            ->setModification(new \DateTime('-1 day'))
        ;

        new \ReflectionProperty(Basket::class, 'id')->setValue($basket, 42);

        return $basket;
    }

    private function item(?string $file = null): ProductItem
    {
        $product = new Product()->setTitle('A book');
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 3);

        $item = new ProductItem()->setTitle('An item')->setPrice(1500)->setProduct($product);

        return null === $file ? $item : $item->setFile(new ProductItemFile()->setName($file));
    }

    private function row(array $rows, string $suffix): array
    {
        foreach ($rows as $row) {
            if (str_ends_with($row['url'], $suffix)) {
                return $row;
            }
        }

        $this->fail('No row was reported for ' . $suffix);
    }
}
