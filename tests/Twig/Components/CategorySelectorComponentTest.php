<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Twig\Components;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Service\ProductCategoryServiceInterface;
use c975L\ShopBundle\Twig\Components\CategorySelectorComponent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// A category picked on "/en/shop" leads to "/en/shop/category/x", the action being answered on a url that carries no language
class CategorySelectorComponentTest extends TestCase
{
    // The page's language travels with the component and picks the localised twin
    public function testACategoryPickedInAnotherLanguageStaysInIt(): void
    {
        $response = $this->component('en', 'catalog')->changeCategory();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('category_display_localized:en:catalog', $response->getTargetUrl());
    }

    // In the writing language, the bare url
    public function testTheWholeShopInTheWritingLanguageIsTheBareUrl(): void
    {
        $response = $this->component('fr', 'all')->changeCategory();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('shop_index::', $response->getTargetUrl());
    }

    private function component(string $pageLocale, string $selected): CategorySelectorComponent
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => $route . ':' . ($parameters['_locale'] ?? '') . ':' . ($parameters['slug'] ?? ''));

        $pageRequest = new Request();
        $pageRequest->setLocale($pageLocale);
        $requestStack = new RequestStack([$pageRequest]);

        $component = new CategorySelectorComponent(
            $this->createStub(ProductCategoryServiceInterface::class),
            new LocalizedUrlGenerator($urlGenerator, new SiteLocales(['fr', 'en'], 'fr'), new RequestStack()),
            $requestStack,
        );
        $component->mount();
        $component->selectedCategory = $selected;

        return $component;
    }
}
