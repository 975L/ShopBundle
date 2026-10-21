<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Service\ProductMerchantFeed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Serves the feed Google Merchant Center fetches on schedule (see ProductMerchantFeed) - rendered on request rather than written under public/ like the sitemaps, so a book added in the morning is in the next fetch without waiting for a deployment
class ProductMerchantFeedController extends AbstractController
{
    public function __construct(
        private readonly ProductMerchantFeed $feed,
    ) {
    }

    // FEED - one per language, "?language=en" for the products written in English, the site's writing language without it (see ProductMerchantFeed). Public like the sheets it is built from, kept out of the index as a file nobody reads, and cached an hour by whoever sits in between
    #[Route(
        '/shop/merchant-feed.xml',
        name: 'shop_merchant_feed',
        methods: ['GET']
    )]
    public function feed(Request $request): Response
    {
        $language = $request->query->get('language');
        if (null !== $language && 1 !== preg_match('/^[a-z]{2,3}$/', $language)) {
            throw new NotFoundHttpException();
        }

        $content = $this->feed->build($language) ?? throw new NotFoundHttpException();

        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
