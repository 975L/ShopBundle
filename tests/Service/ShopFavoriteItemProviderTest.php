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
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ShopFavoriteItemProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The one place of this bundle that knows what "shop_product" stands for, UiBundle's wishlist storing a name and an id and nothing else
class ShopFavoriteItemProviderTest extends TestCase
{
    public function testItOnlyAnswersForItsOwnKindOfThing(): void
    {
        $provider = $this->provider([]);

        $this->assertTrue($provider->supports('shop_product'));
        $this->assertFalse($provider->supports('book'));
    }

    public function testAProductBecomesACardPointingAtItsSheet(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->setDescription('<p>Une <strong>affiche</strong> A2</p>');

        $items = $this->provider([$product])->getItems('shop_product', [0]);
        $item = reset($items);

        $this->assertSame('Affiche', $item->title);
        $this->assertSame('Une affiche A2', $item->description);
        $this->assertSame('https://example.org/product_display/affiche', $item->url);
    }

    // The same hundred characters the product's own card shows, the description being rich text where a card carries the words only
    public function testALongDescriptionIsCutWhereTheProductsOwnCardCutsIt(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche')->setDescription(str_repeat('a', 150));

        $items = $this->provider([$product])->getItems('shop_product', [0]);
        $item = reset($items);

        $this->assertSame(str_repeat('a', 100) . '…', $item->description);
    }

    public function testTheFirstPictureOfTheGalleryIsTheCardsOwn(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche');
        $product->addMedia(new ProductMedia()->setName('medias/shop/products/affiche.webp'));

        $items = $this->provider([$product])->getItems('shop_product', [0]);
        $item = reset($items);

        $this->assertSame('/medias/shop/products/affiche.webp', $item->imageUrl);
    }

    // A product carrying no picture shows a card with none rather than a placeholder nobody chose
    public function testAProductWithoutAPictureCarriesNoImage(): void
    {
        $product = new Product()->setTitle('Affiche')->setSlug('affiche');

        $items = $this->provider([$product])->getItems('shop_product', [0]);
        $item = reset($items);

        $this->assertNull($item->imageUrl);
    }

    // Whatever the visitor may no longer see is left out by the repository itself, so nothing here has to know what "published" means
    public function testWhatTheRepositoryLeavesOutIsNotDrawn(): void
    {
        $this->assertSame([], $this->provider([])->getItems('shop_product', [12, 39]));
    }

    /**
     * @param Product[] $products
     */
    private function provider(array $products): ShopFavoriteItemProvider
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findAvailableByIds')->willReturn($products);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => 'https://example.org/' . $route . (isset($parameters['slug']) ? '/' . $parameters['slug'] : '')
        );

        return new ShopFavoriteItemProvider($repository, $urlGenerator, new Packages(new PathPackage('/', new EmptyVersionStrategy())));
    }
}
