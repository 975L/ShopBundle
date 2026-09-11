<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Builds the public urls of the three screens this bundle answers - the single place they are spelled, so a breadcrumb, a sitemap and a hreflang group can never drift from one another. Same shape as SiteBundle's PagePublicUrlResolver and BookBundle's BookPublicUrlResolver: the host comes from "site-url" rather than from the request context, since the sitemap is written from a cron command where there is no request to carry it
class ShopPublicUrlResolver
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // The canonical absolute url of a screen, the writing language's own whichever language a visitor is reading - null while "site-url" isn't configured yet, a sitemap accepting no relative url
    /** @param array<string, mixed> $parameters */
    public function resolve(string $route, array $parameters = []): ?string
    {
        $siteUrl = $this->siteUrl();

        return null === $siteUrl ? null : $siteUrl . $this->urlGenerator->generate($route, $parameters);
    }

    // The same screen absolute, in the language the page around it is being read in - what a breadcrumb prints and what the BreadcrumbList declares, falling back on the router's own absolute url while "site-url" isn't configured
    /** @param array<string, mixed> $parameters */
    public function resolveLocalizedUrl(string $route, array $parameters = []): string
    {
        $siteUrl = $this->siteUrl();

        return null === $siteUrl
            ? $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL)
            : $siteUrl . $this->localizedUrlGenerator->path($route, $parameters);
    }

    // One absolute url per language the screen answers in, for a sitemap's alternates - empty below two languages, while "site-url" is unconfigured, and where the writing language is missing, a group naming itself alone repeating the canonical and one not naming itself being invalid (the contract of PagePublicUrlResolver::alternatesFor())
    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $locales
     *
     * @return array<string, string> hreflang => absolute url
     */
    public function resolveAlternates(string $route, array $parameters, array $locales): array
    {
        $siteUrl = $this->siteUrl();
        if (null === $siteUrl || \count($locales) < 2 || !\in_array($this->siteLocales->getDefaultLocale(), $locales, true)) {
            return [];
        }

        $alternates = [];
        foreach ($locales as $locale) {
            try {
                $alternates[$locale] = $siteUrl . $this->pathFor($route, $parameters, $locale);
            } catch (RoutingExceptionInterface) {
                // No localised twin, or none answering for these parameters: there is no group to declare rather than a sitemap that cannot be written at all
                return [];
            }
        }

        return $alternates;
    }

    // A named language rather than the one being read: the sitemap is written without a request, so the localised twin is asked of the router directly - the suffix being the generator's own, a renamed pair stays one thing to change
    /** @param array<string, mixed> $parameters */
    private function pathFor(string $route, array $parameters, string $locale): string
    {
        return $locale === $this->siteLocales->getDefaultLocale()
            ? $this->urlGenerator->generate($route, $parameters)
            : $this->urlGenerator->generate($route . LocalizedUrlGenerator::LOCALIZED_SUFFIX, $parameters + ['_locale' => $locale]);
    }

    // The configured host without its trailing slash, null when unconfigured - every generated path already opens with a slash, and a "site-url" saved as "https://example.com/" would otherwise double it
    private function siteUrl(): ?string
    {
        $siteUrl = trim((string) $this->configService->get('site-url'));

        return '' === $siteUrl ? null : rtrim($siteUrl, '/');
    }
}
