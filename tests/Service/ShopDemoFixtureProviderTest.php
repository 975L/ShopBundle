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
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Service\ShopDemoFixtureProvider;
use c975L\ShopBundle\Service\ShopSampleCatalog;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

class ShopDemoFixtureProviderTest extends TestCase
{
    private const string IMAGE = 'showcase/photo.webp';
    private const string DOCUMENT = 'showcase/guide.pdf';

    private string $projectDir;

    /** @var list<string> */
    private array $temporaryCopies = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/shop-demo-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/showcase');
        file_put_contents($this->projectDir . '/public/' . self::IMAGE, 'image');
        file_put_contents($this->projectDir . '/public/' . self::DOCUMENT, 'document');
    }

    // The copies handed to VichUploader live in the system's temp directory, where a real load has them moved away - nothing moves them here, so the test takes them back itself
    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->projectDir, ...$this->temporaryCopies]);
    }

    /** @param array<string, list<string>> $keyed */
    private function createProvider(array $images = [self::IMAGE], ?string $document = self::DOCUMENT, array $keyed = []): ShopDemoFixtureProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn($images);
        $registry->method('getDocument')->willReturn($document);
        $registry->method('getImagesFor')->willReturnCallback(static fn (string $key): array => $keyed[$key] ?? []);

        return new ShopDemoFixtureProvider(new ShopSampleCatalog(), $translator, $registry, $this->projectDir);
    }

    /** @return list<object> */
    private function fixtures(ShopDemoFixtureProvider $provider): array
    {
        $fixtures = iterator_to_array($provider->getDemoFixtures(), false);

        foreach ($fixtures as $entity) {
            if (!$entity instanceof Product) {
                continue;
            }

            foreach ($entity->getMedias() as $media) {
                $this->temporaryCopies[] = (string) $media->getFile()?->getPathname();
            }
            foreach ($entity->getItems() as $item) {
                $this->temporaryCopies[] = (string) $item->getFile()?->getFile()?->getPathname();
            }
        }

        return $fixtures;
    }

    /** @return list<Product> */
    private function products(ShopDemoFixtureProvider $provider): array
    {
        return array_values(array_filter($this->fixtures($provider), fn (object $entity): bool => $entity instanceof Product));
    }

    // Categories come first, a product being attached to one of them as it is built
    public function testCategoriesAreYieldedBeforeTheProductsThatUseThem(): void
    {
        $types = array_map(fn (object $entity): string => $entity::class, $this->fixtures($this->createProvider()));
        $categories = array_keys($types, ProductCategory::class, true);

        $this->assertSame(range(0, count($categories) - 1), $categories);
    }

    public function testEveryProductIsShownDatedAndFiledUnderItsCategory(): void
    {
        foreach ($this->products($this->createProvider()) as $product) {
            $this->assertFalse($product->isHidden(), (string) $product->getSlug());
            $this->assertNotNull($product->getCreation());
            $this->assertCount(1, $product->getCategories(), (string) $product->getSlug());
        }
    }

    public function testEveryProductOfTheCatalogIsSeededWithItsItems(): void
    {
        $catalog = new ShopSampleCatalog();
        $products = $this->products($this->createProvider());

        $this->assertSame(array_column($catalog->getProducts(), 'slug'), array_map(fn (Product $product): ?string => $product->getSlug(), $products));

        foreach ($products as $index => $product) {
            $this->assertCount(count($catalog->getProducts()[$index]['items']), $product->getItems(), (string) $product->getSlug());
        }
    }

    // The upload moves the file it is handed, so the placeholder every other showcase of the site reads has to survive the load
    public function testTheMediaCarriesACopyAndNeverThePlaceholderItself(): void
    {
        $product = $this->products($this->createProvider())[0];
        $media = $product->getMedias()->first();

        $this->assertNotFalse($media);
        // A plain File is what UploadHandler::hasUploadedFile() silently ignores: the row would be written with no file name and nothing would reach the disk
        $this->assertInstanceOf(ReplacingFile::class, $media->getFile());
        $this->assertNotSame($this->projectDir . '/public/' . self::IMAGE, $media->getFile()->getPathname());
        $this->assertFileExists($this->projectDir . '/public/' . self::IMAGE);
        $this->assertFileExists($media->getFile()->getPathname());
    }

    // Unlike the showcase, which shows nothing at all: a card falls back on its own "no image" picture, where an empty catalog leaves nothing to browse
    public function testASiteDeclaringNoPlaceholderStillGetsItsCatalogWithoutPictures(): void
    {
        $products = $this->products($this->createProvider(images: []));

        $this->assertCount(count(new ShopSampleCatalog()->getProducts()), $products);

        foreach ($products as $product) {
            $this->assertCount(0, $product->getMedias());
        }
    }

    public function testADownloadedItemCarriesTheDocumentPlaceholder(): void
    {
        $files = $this->itemFiles($this->createProvider());

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertInstanceOf(ReplacingFile::class, $file);
            $this->assertFileExists($file->getPathname());
        }
    }

    /** @return list<\Symfony\Component\HttpFoundation\File\File> */
    private function itemFiles(ShopDemoFixtureProvider $provider): array
    {
        $files = [];
        foreach ($this->products($provider) as $product) {
            foreach ($product->getItems() as $item) {
                if (null !== $item->getFile()?->getFile()) {
                    $files[] = $item->getFile()->getFile();
                }
            }
        }

        return $files;
    }

    // The photographs a site declares for one product are all attached, in the order its slider leafs through them - which a single rotated placeholder can never stand for
    public function testADeclaredProductCarriesEveryPictureInOrder(): void
    {
        $pictures = ['showcase/chaise-1.webp', 'showcase/chaise-2.webp', 'showcase/chaise-3.webp'];

        foreach ($pictures as $picture) {
            file_put_contents($this->projectDir . '/public/' . $picture, 'image');
        }

        $provider = $this->createProvider(keyed: ['shop/chaise-bistrot' => $pictures]);

        foreach ($this->fixtures($provider) as $entity) {
            if ($entity instanceof Product && 'chaise-bistrot' === $entity->getSlug()) {
                $positions = array_map(static fn (ProductMedia $media): ?int => $media->getPosition(), $entity->getMedias()->toArray());

                $this->assertSame([1, 2, 3], array_values($positions));

                return;
            }
        }

        $this->fail('no product "chaise-bistrot"');
    }

    // A product the site declares nothing for keeps the rotated placeholder, so a shop is never left with empty cards where it used to have some
    public function testAProductWithoutADeclaredPictureFallsBackOnThePool(): void
    {
        $provider = $this->createProvider(keyed: ['shop/chaise-bistrot' => []]);

        foreach ($this->fixtures($provider) as $entity) {
            if ($entity instanceof Product && 'table-basse-chene' === $entity->getSlug()) {
                $this->assertCount(1, $entity->getMedias());

                return;
            }
        }

        $this->fail('no product "table-basse-chene"');
    }
}
