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
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductJsonLdClient;
use c975L\ShopBundle\Service\ProductServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Checks that a product sheet really serves the Product graph this bundle builds for it, for ConfigBundle's "Health check" dashboard page (see HealthCheckProviderInterface, run only from c975l:health-check:run). The check belongs here rather than in ConfigBundle: it is only a defect from the moment something is supposed to write that markup
class ProductStructuredDataHealthCheckProvider implements HealthCheckProviderInterface
{
    public function __construct(
        private readonly ProductServiceInterface $productService,
        private readonly ProductJsonLdClient $jsonLdClient,
        private readonly UrlStatusChecker $urlStatusChecker,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'product-json-ld';
    }

    public function runChecks(): array
    {
        // Fetching a sheet means asking the site for it by its own address, which nothing knows before "site-url" is configured - the same reason the sitemap declares nothing then
        $urlRoot = rtrim((string) $this->configService->get('site-url'), '/');
        if ('' === $urlRoot) {
            return [];
        }

        $results = [];
        foreach ($this->productService->findAll() as $product) {
            $results[] = $this->checkProduct($product, $urlRoot . '/shop/products/' . $product->getSlug());
        }

        return $results;
    }

    private function checkProduct(Product $product, string $url): array
    {
        $label = (string) $product->getTitle();
        $editUrl = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($product->getId())
            ->generateUrl()
        ;

        if (!$this->urlStatusChecker->exists($url)) {
            return $this->row($url, $label, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_product_not_served', [], [], $editUrl);
        }

        try {
            $found = $this->jsonLdClient->readStructuredData($url);
        } catch (\Throwable $e) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_product_json_ld_call_failed', ['%message%' => $e->getMessage()], ['error' => $e->getMessage()], $editUrl);
        }

        // Nothing at all, then something that does not parse: two different things to fix, and the second one is invisible to whoever reads the page
        if (0 === $found['blocks']) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_product_json_ld_missing', [], $found, $editUrl);
        }

        if ($found['invalid'] > 0) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_product_json_ld_invalid', ['%count%' => $found['invalid']], $found, $editUrl);
        }

        // A sheet carrying only the breadcrumb graph is served, parses, and still says nothing about what is sold on it
        if (!\in_array('Product', $found['types'], true)) {
            return $this->row($url, $label, HealthCheckResult::STATUS_WARNING, 'label.health_check_product_json_ld_no_product', [], $found, $editUrl);
        }

        return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_product_json_ld_ok', [], $found, $editUrl);
    }

    private function row(string $url, string $label, string $status, string $summaryKey, array $parameters, array $details, string $editUrl): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $this->translator->trans($summaryKey, $parameters, 'shop'),
            'details' => $details,
            'editUrl' => $editUrl,
        ];
    }
}
