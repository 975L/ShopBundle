<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Service\ProductServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use c975L\ShopBundle\Entity\ProductItemDownload;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
class ProductController extends AbstractController
{
    public function __construct(private readonly ProductServiceInterface $productService)
    {
    }

    // DISPLAY
    #[Route(
        '/shop/products/{slug:product}',
        name: 'product_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET']
    )]
    public function display(Product $product): Response
    {
        return $this->render(
            '@c975LShop/product/display.html.twig',
            [
                'product' => $product,
            ]
        )->setMaxAge(3600);
    }
}
