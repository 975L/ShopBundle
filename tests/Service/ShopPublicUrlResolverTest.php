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
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Service\ShopPublicUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShopPublicUrlResolverTest extends TestCase
{
    // The canonical is the writing language's own, whichever language the visitor happens to be reading
    public function testTheCanonicalUrlIsTheWritingLanguages(): void
    {
        $this->assertSame(
            'https://example.org/shop/products/table-basse',
            $this->resolver('en')->resolve('product_display', ['slug' => 'table-basse']),
        );
    }

    // A sitemap accepts no relative url, so there is nothing to declare before "site-url" is configured
    public function testNoCanonicalUrlWhileTheSiteUrlIsUnconfigured(): void
    {
        $this->assertNull($this->resolver('en', siteUrl: '')->resolve('shop_index'));
    }

    // A trailing slash on the configured host would otherwise double the one every generated path opens with
    public function testTheConfiguredHostLosesItsTrailingSlash(): void
    {
        $this->assertSame('https://example.org/shop', $this->resolver(null, siteUrl: 'https://example.org/')->resolve('shop_index'));
    }

    // What a breadcrumb prints: the page the visitor really has open, in the language he is reading it in
    public function testTheLocalizedUrlFollowsTheLanguageBeingRead(): void
    {
        $this->assertSame('https://example.org/en/shop', $this->resolver('en')->resolveLocalizedUrl('shop_index'));
        $this->assertSame('https://example.org/shop', $this->resolver(null)->resolveLocalizedUrl('shop_index'));
    }

    // Without a configured host the trail is the one this bundle has always printed, the router taking the host from the request
    public function testTheLocalizedUrlFallsBackOnTheRouterWhileTheSiteUrlIsUnconfigured(): void
    {
        $this->assertSame('https://example.org/shop', $this->resolver('en', siteUrl: '')->resolveLocalizedUrl('shop_index'));
    }

    // One entry per language, the writing one included: that is what makes the group valid
    public function testTheAlternatesNameEveryLanguageTheScreenAnswersIn(): void
    {
        $this->assertSame(
            ['fr' => 'https://example.org/shop', 'en' => 'https://example.org/en/shop'],
            $this->resolver(null)->resolveAlternates('shop_index', [], ['fr', 'en']),
        );
    }

    // A group naming itself alone repeats what the canonical already said, and one not naming the writing language at all is invalid
    public function testNoAlternatesForASingleLanguageOrWithoutTheWritingOne(): void
    {
        $resolver = $this->resolver(null);

        $this->assertSame([], $resolver->resolveAlternates('shop_index', [], ['fr']));
        $this->assertSame([], $resolver->resolveAlternates('shop_index', [], ['en', 'es']));
        $this->assertSame([], $this->resolver(null, siteUrl: '')->resolveAlternates('shop_index', [], ['fr', 'en']));
    }

    private function resolver(?string $readingLocale, string $siteUrl = 'https://example.org'): ShopPublicUrlResolver
    {
        $request = Request::create('https://example.org/shop');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = [], int $type = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $path = match ($route) {
                    'shop_index' => '/shop',
                    'shop_index_localized' => '/' . $parameters['_locale'] . '/shop',
                    'product_display' => '/shop/products/' . $parameters['slug'],
                    'product_display_localized' => '/' . $parameters['_locale'] . '/shop/products/' . $parameters['slug'],
                    default => throw new RouteNotFoundException($route),
                };

                return UrlGeneratorInterface::ABSOLUTE_URL === $type ? 'https://example.org' . $path : $path;
            }
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $siteLocales = new SiteLocales(['fr', 'en'], 'fr');

        return new ShopPublicUrlResolver(
            $configService,
            new LocalizedUrlGenerator($router, $siteLocales, new RequestStack([$request])),
            $router,
            $siteLocales,
        );
    }
}
