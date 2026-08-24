<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckErrorRow;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Repository\ProductItemDownloadRepository;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Service\ProductItemDownloadService;
use c975L\ShopBundle\Service\ProductItemDownloadServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the catalogue and the deliveries hide from every screen - a file paid for and never handed over, a file on sale that left the server, an article sold past its stock - none of which shows up until a customer runs into it: the catalogue-side counterpart of PaymentBundle's BasketIntegrityHealthCheckProvider, which reads the orders themselves
class ShopIntegrityHealthCheckProvider implements HealthCheckProviderInterface
{
    public const string KIND = 'shop-integrity';

    // Suffixes the rows are keyed by, appended to the site root so each check keeps a history of its own
    public const string ROW_UNDELIVERED_DOWNLOADS = '#undelivered-downloads';
    public const string ROW_MISSING_FILES = '#missing-files';
    public const string ROW_OVERSOLD_ITEMS = '#oversold-items';
    public const string ROW_FREE_ITEMS = '#free-items';

    // The copies are made by a message handler, so an order paid moments before the run is being delivered rather than left undelivered
    private const int GRACE_MINUTES = 60;

    private const int MAX_OFFENDERS = 50;

    // Read once for the three checks that walk the catalogue, rather than once each
    private ?array $sellable = null;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly ProductItemRepository $productItemRepository,
        private readonly ProductItemDownloadRepository $downloadRepository,
        private readonly ProductItemDownloadServiceInterface $itemDownloadService,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        // Same guard as every site-wide check: without a site url there is nothing to key a row on
        $siteRoot = $this->siteUrlResolver->siteRoot();
        if (null === $siteRoot) {
            return [];
        }

        return [
            $this->guard($siteRoot . self::ROW_UNDELIVERED_DOWNLOADS, 'label.health_check_shop_undelivered_downloads', HealthCheckResult::STATUS_ERROR, fn () => $this->undeliveredDownloads()),
            $this->guard($siteRoot . self::ROW_MISSING_FILES, 'label.health_check_shop_missing_files', HealthCheckResult::STATUS_ERROR, fn () => $this->missingFiles()),
            $this->guard($siteRoot . self::ROW_OVERSOLD_ITEMS, 'label.health_check_shop_oversold_items', HealthCheckResult::STATUS_ERROR, fn () => $this->oversoldItems()),
            $this->guard($siteRoot . self::ROW_FREE_ITEMS, 'label.health_check_shop_free_items', HealthCheckResult::STATUS_WARNING, fn () => $this->freeItems()),
        ];
    }

    // The orders holding a file whose copy was never made, read no further back than a link lives - past that the nightly purge has taken every order's copies away (see ProductItemDownloadService::purgeExpired())
    private function undeliveredDownloads(): array
    {
        $orders = $this->recentDigitalOrders();
        $delivered = $this->downloadRepository->findDeliveredBasketIds(array_map(static fn (Basket $basket) => (int) $basket->getId(), $orders));

        $offenders = [];
        foreach ($orders as $basket) {
            if (\in_array((int) $basket->getId(), $delivered, true)) {
                continue;
            }

            $offenders[] = [
                'basketId' => $basket->getId(),
                'label' => $basket->getNumber() ?? ('#' . $basket->getId()),
                'info' => \count($this->itemDownloadService->getFileItems($basket->getItems())) . ' file(s) - ' . $basket->getEmail(),
            ];
        }

        return \array_slice($offenders, 0, self::MAX_OFFENDERS);
    }

    // The orders of the last few days holding at least one file, settled long enough ago for their delivery to have run
    private function recentDigitalOrders(): array
    {
        $before = new \DateTime('-' . self::GRACE_MINUTES . ' minutes');

        return array_values(array_filter(
            $this->basketRepository->findOrdersSince(new \DateTime('-' . ProductItemDownloadService::VALIDITY_DAYS . ' days')),
            fn (Basket $basket) => $basket->getModification() < $before && [] !== $this->itemDownloadService->getFileItems($basket->getItems()),
        ));
    }

    // A file on sale that is no longer where it is read from: the sheet still offers it, the checkout still takes the money, and the delivery skips the item rather than failing (see ProductItemDownloadMessageHandler)
    private function missingFiles(): array
    {
        $offenders = [];

        foreach ($this->sellable() as $item) {
            $file = $item->getFile();
            $name = $file?->getName();

            if (null === $file || null === $name) {
                continue;
            }

            if (!is_file($this->projectDir . '/' . $file->getPrivateDirectory() . '/' . $name)) {
                $offenders[] = $this->offender($item, $name);
            }
        }

        return \array_slice($offenders, 0, self::MAX_OFFENDERS);
    }

    // Sold past what was declared: the stock left reads as a negative number nobody is shown, and the shop goes on taking orders it cannot fill
    private function oversoldItems(): array
    {
        $offenders = [];

        foreach ($this->sellable() as $item) {
            $limited = $item->getLimitedQuantity();

            if (null !== $limited && (int) $item->getOrderedQuantity() > $limited) {
                $offenders[] = $this->offender($item, $item->getOrderedQuantity() . ' ordered of ' . $limited);
            }
        }

        return \array_slice($offenders, 0, self::MAX_OFFENDERS);
    }

    // An article on sale for nothing: a story given away and a price cleared by an edit look alike, hence a warning - and reported only as the exception it is, a catalogue giving away more than it sells being a business decision
    private function freeItems(): ?array
    {
        $offenders = [];

        foreach ($this->sellable() as $item) {
            if ((int) $item->getPrice() <= 0) {
                $offenders[] = $this->offender($item, 'price ' . (int) $item->getPrice());
            }
        }

        if ([] !== $offenders && \count($offenders) * 2 >= \count($this->sellable())) {
            return null;
        }

        return \array_slice($offenders, 0, self::MAX_OFFENDERS);
    }

    // Every item on sale, read once per run
    private function sellable(): array
    {
        return $this->sellable ??= $this->productItemRepository->findSellable();
    }

    // The article as the dashboard names it, its product carried alongside so the advice can link to the screen it is edited from
    private function offender(ProductItem $item, string $info): array
    {
        return [
            'productId' => $item->getProduct()?->getId(),
            'label' => $item->getProduct()?->getTitle() . ' / ' . $item->getTitle(),
            'info' => $info,
        ];
    }

    // Runs one check and turns what it found into its row, one that blows up saying so rather than taking the others with it: HealthCheckRunner drops every row of a provider that throws, and a dashboard left with no row at all reads as "no problem"
    private function guard(string $url, string $labelId, string $failedStatus, callable $check): array
    {
        $label = $this->translator->trans($labelId, [], 'shop');

        try {
            $offenders = $check();
        } catch (\Throwable $e) {
            return HealthCheckErrorRow::build($this->translator, 'shop', $url, $label, 'label.health_check_shop_check_failed', $e->getMessage());
        }

        // A check with nothing to say on this shop says so rather than passing: green would claim it looked and found nothing
        if (null === $offenders) {
            return [
                'url' => $url,
                'label' => $label,
                'status' => HealthCheckResult::STATUS_SKIPPED,
                'summary' => $this->translator->trans($labelId . '_skipped', [], 'shop'),
                'details' => ['offenders' => []],
            ];
        }

        return [
            'url' => $url,
            'label' => $label,
            'status' => [] === $offenders ? HealthCheckResult::STATUS_OK : $failedStatus,
            'summary' => $this->translator->trans($labelId . ([] === $offenders ? '_ok' : '_ko'), ['%count%' => \count($offenders)], 'shop'),
            'details' => ['offenders' => $offenders],
        ];
    }
}
