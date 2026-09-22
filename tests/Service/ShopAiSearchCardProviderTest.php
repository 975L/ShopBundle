<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopAiSearchCardProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

// A product the site search answered from is drawn as the shop's own card, every other url staying a plain link
class ShopAiSearchCardProviderTest extends TestCase
{
    public function testOnlyTheProductUrlsTheShopStillSellsBecomeCards(): void
    {
        // The search arrives as a POST, while the product routes only accept GET
        $context = new RequestContext(method: 'POST');
        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context);
        $router->method('match')->willReturnCallback(fn (string $path): array => 'GET' !== $context->getMethod() ? throw new MethodNotAllowedException(['GET']) : match ($path) {
            '/shop/products/affiche' => ['_route' => 'product_display', 'slug' => 'affiche'],
            '/en/shop/products/poster' => ['_route' => 'product_display_localized', 'slug' => 'poster', '_locale' => 'en'],
            '/shop/products/retire' => ['_route' => 'product_display', 'slug' => 'retire'],
            '/pages/contact' => ['_route' => 'page_display', 'slug' => 'contact'],
            default => throw new ResourceNotFoundException(),
        });

        // "retire" is no longer on sale, so the repository leaves it out
        $repository = $this->createMock(ProductRepository::class);
        $repository->expects($this->once())->method('findAvailableBySlugs')->with(['affiche', 'poster', 'retire'])->willReturn([
            new Product()->setSlug('affiche'),
            new Product()->setSlug('poster'),
        ]);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(fn (string $template, array $context): string => '<card>' . $context['product']->getSlug() . '</card>');

        $cards = new ShopAiSearchCardProvider($router, $repository, $twig)->renderCards([
            'https://site.example/shop/products/affiche',
            'https://site.example/en/shop/products/poster',
            'https://site.example/shop/products/retire',
            'https://site.example/pages/contact',
            'https://site.example/unknown',
        ]);

        $this->assertSame([
            'https://site.example/shop/products/affiche' => '<card>affiche</card>',
            'https://site.example/en/shop/products/poster' => '<card>poster</card>',
        ], $cards);
        $this->assertSame('POST', $context->getMethod());
    }
}
