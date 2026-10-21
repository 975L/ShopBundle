<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller;

use c975L\ShopBundle\Controller\ProductMerchantFeedController;
use c975L\ShopBundle\Service\ProductMerchantFeed;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// The feed's only door: a language as the product field stores it, a 404 when there is no feed, and a cached file kept out of the index
class ProductMerchantFeedControllerTest extends TestCase
{
    // A language the product field cannot store is not a feed
    public function testAnInvalidLanguageIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller('<rss/>')->feed(Request::create('/shop/merchant-feed.xml', 'GET', ['language' => 'en-GB']));
    }

    // No feed built, no site url or a shop in test mode, is a 404
    public function testNoFeedIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(null)->feed(Request::create('/shop/merchant-feed.xml'));
    }

    // The feed is served as XML, cached an hour and never indexed, a three-letter language being accepted
    public function testTheFeedIsServedCachedAndNotIndexed(): void
    {
        $response = $this->controller('<rss/>')->feed(Request::create('/shop/merchant-feed.xml', 'GET', ['language' => 'haw']));

        $this->assertSame('<rss/>', $response->getContent());
        $this->assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    private function controller(?string $content): ProductMerchantFeedController
    {
        $feed = $this->createStub(ProductMerchantFeed::class);
        $feed->method('build')->willReturn($content);

        return new ProductMerchantFeedController($feed);
    }
}
