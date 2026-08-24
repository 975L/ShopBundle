<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Management\ProductImportProvider;
use c975L\ShopBundle\Repository\MediaRepository;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

class ProductImportProviderTest extends TestCase
{
    // The entities the provider handed to the entity manager, in the order it persisted them
    private array $persisted = [];

    public function testSupportsImportOnlyMatchesShopProductKind(): void
    {
        $provider = $this->createProvider(sys_get_temp_dir());

        $this->assertTrue($provider->supportsImport('shop_product'));
        $this->assertFalse($provider->supportsImport('shop_product_category'));
    }

    // The whole product, its category created on the fly and its item's two files laid straight back under the names they were served under
    public function testImportCreatesTheProductWithItsCategoryItsItemAndItsFiles(): void
    {
        $projectDir = $this->createDir();
        $filesDir = $this->createDir();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($filesDir . '/files/aaa_picture.webp', 'picture-bytes');
        $filesystem->dumpFile($filesDir . '/files/bbb_paid.pdf', 'paid-bytes');

        $provider = $this->createProvider($projectDir);
        $result = $provider->import([$this->productData()], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $product = $this->persistedOf(Product::class)[0];
        $this->assertSame('affiche-montagne', $product->getSlug());
        $this->assertTrue($product->isPublished());
        // Put back over what ProductListener stamped on the flush, which is what the "new" badge is counted from
        $this->assertSame('2026-01-15', $product->getCreation()->format('Y-m-d'));

        $category = $this->persistedOf(ProductCategory::class)[0];
        $this->assertSame('affiches', $category->getSlug());
        $this->assertSame('Affiches', $category->getName());

        $item = $product->getItems()->first();
        $this->assertSame('A3', $item->getTitle());
        $this->assertSame(2500, $item->getPrice());

        // Laid back under their exported names, so the catalogue answers at the very same urls on this site as on the one it was exported from
        $this->assertSame('picture-bytes', file_get_contents($projectDir . '/public/medias/shop/products/affiche-montagne-1.webp'));
        $this->assertSame('paid-bytes', file_get_contents($projectDir . '/private/medias/shop/items/affiche-montagne-a3-2.pdf'));
        $this->assertSame('medias/shop/products/affiche-montagne-1.webp', $product->getMedias()->first()->getName());
        $this->assertSame(\strlen('paid-bytes'), $item->getFile()->getSize());
        // Nothing went through the upload pipeline, so no file was ever handed to Vich
        $this->assertNull($product->getMedias()->first()->getFile());

        $filesystem->remove([$projectDir, $filesDir]);
    }

    // A name this bundle would refuse to write ("../" out of the media directory, a path of its own) falls back on Vich naming the file itself rather than laying bytes wherever the archive asked
    public function testImportFallsBackOnVichNamingForAnUnsafeArchivedName(): void
    {
        $projectDir = $this->createDir();
        $filesDir = $this->createDir();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($filesDir . '/files/aaa_picture.webp', 'picture-bytes');

        $data = $this->productData();
        $data['medias'][0]['name'] = '../../etc/passwd';
        unset($data['items']);

        $this->createProvider($projectDir)->import([$data], $filesDir);

        $product = $this->persistedOf(Product::class)[0];
        $this->assertInstanceOf(ReplacingFile::class, $product->getMedias()->first()->getFile());
        $this->assertFalse(is_file($projectDir . '/etc/passwd'));

        $filesystem->remove([$projectDir, $filesDir]);
    }

    // A product naming one that only comes further down the same manifest still gets linked to it
    public function testImportLinksRelatedProductsOfTheSameArchive(): void
    {
        $first = $this->productData();
        $first['medias'] = [];
        $first['items'] = [];
        $first['relatedProducts'] = ['cadre-bois'];

        $second = $first;
        $second['slug'] = 'cadre-bois';
        $second['title'] = 'Cadre bois';
        $second['relatedProducts'] = [];

        $this->createProvider($this->createDir())->import([$first, $second]);

        $products = $this->persistedOf(Product::class);
        $this->assertSame('cadre-bois', $products[0]->getRelatedProducts()->first()->getSlug());
    }

    // The upsert path: a product this environment already holds is written over rather than doubled, its item and its picture matched by their own keys - and a picture the archive dropped goes with it, being one drag to upload again
    public function testImportWritesOverTheProductAlreadyHeldUnderThatSlug(): void
    {
        $projectDir = $this->createDir();
        $filesDir = $this->createDir();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($filesDir . '/files/aaa_picture.webp', 'new-picture-bytes');
        $filesystem->dumpFile($filesDir . '/files/bbb_paid.pdf', 'new-paid-bytes');

        $existing = new Product()->setSlug('affiche-montagne')->setTitle('Ancien titre')->setDescription('Ancienne description');
        $keptMedia = new ProductMedia()->setName('medias/shop/products/affiche-montagne-1.webp')->setAlt('Ancien alt');
        $droppedMedia = new ProductMedia()->setName('medias/shop/products/affiche-montagne-9.webp');
        $existing->addMedia($keptMedia);
        $existing->addMedia($droppedMedia);
        $existingItem = new ProductItem()->setTitle('A3')->setSlug('a3')->setDescription('Ancien format')->setPrice(1000);
        $existing->addItem($existingItem);

        $result = $this->createProvider($projectDir, $existing)->import([$this->productData()], $filesDir);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Affiche montagne', $existing->getTitle());

        // The picture the archive still names keeps its row, and takes the archived bytes
        $this->assertCount(1, $existing->getMedias());
        $this->assertSame($keptMedia, $existing->getMedias()->first());
        $this->assertSame('Affiche', $keptMedia->getAlt());
        $this->assertSame('new-picture-bytes', file_get_contents($projectDir . '/public/medias/shop/products/affiche-montagne-1.webp'));

        // The item is matched by its slug rather than doubled, and its paid file written over
        $this->assertCount(1, $existing->getItems());
        $this->assertSame($existingItem, $existing->getItems()->first());
        $this->assertSame(2500, $existingItem->getPrice());
        $this->assertSame('new-paid-bytes', file_get_contents($projectDir . '/private/medias/shop/items/affiche-montagne-a3-2.pdf'));

        $filesystem->remove([$projectDir, $filesDir]);
    }

    // An item this environment holds and the archive does not name is left where it is: its file is what a customer has paid for, and no re-upload brings it back
    public function testImportLeavesAnItemTheArchiveDoesNotNameInPlace(): void
    {
        $existing = new Product()->setSlug('affiche-montagne')->setTitle('Affiche montagne');
        $sold = new ProductItem()->setTitle('A2')->setSlug('a2')->setDescription('Format A2')->setPrice(4000);
        $existing->addItem($sold);

        $data = $this->productData();
        $data['medias'] = [];
        $data['items'] = [];

        $this->createProvider($this->createDir(), $existing)->import([$data]);

        $this->assertCount(1, $existing->getItems());
        $this->assertSame($sold, $existing->getItems()->first());
    }

    // The column travels with the archive, and one written before it existed carries items that were all on sale
    public function testAnItemComesBackOnSaleOrOfflineAsTheArchiveNamesIt(): void
    {
        $projectDir = $this->createDir();
        $filesDir = $this->createDir();
        new Filesystem()->dumpFile($filesDir . '/files/aaa_picture.webp', 'picture-bytes');

        $data = $this->productData();
        unset($data['items'][0]['file']);
        $data['items'][0]['isPublished'] = false;
        $data['items'][] = ['slug' => 'a4', 'title' => 'A4', 'description' => 'Format A4', 'price' => 1500, 'currency' => 'eur', 'vat' => 20, 'position' => 1, 'media' => null, 'file' => null];

        $this->createProvider($projectDir)->import([$data], $filesDir);

        $items = $this->persistedOf(Product::class)[0]->getItems();
        $this->assertFalse($items->first()->isPublished());
        $this->assertTrue($items->last()->isPublished());

        new Filesystem()->remove([$projectDir, $filesDir]);
    }

    // An archive naming no limited quantity means an item sold without a stock to run out of, which is not the same as one limited to zero
    public function testAnItemNamingNoLimitedQuantityComesBackUnlimitedRatherThanOutOfStock(): void
    {
        $projectDir = $this->createDir();
        $filesDir = $this->createDir();
        new Filesystem()->dumpFile($filesDir . '/files/aaa_picture.webp', 'picture-bytes');

        $data = $this->productData();
        unset($data['items'][0]['file']);
        $data['items'][] = ['slug' => 'a4', 'title' => 'A4', 'description' => 'Format A4', 'price' => 1500, 'currency' => 'eur', 'vat' => 20, 'position' => 1, 'limitedQuantity' => 0, 'media' => null, 'file' => null];

        $this->createProvider($projectDir)->import([$data], $filesDir);

        $items = $this->persistedOf(Product::class)[0]->getItems();
        $this->assertNull($items->first()->getLimitedQuantity());
        $this->assertSame(0, $items->last()->getLimitedQuantity());

        new Filesystem()->remove([$projectDir, $filesDir]);
    }

    private function productData(): array
    {
        return [
            'slug' => 'affiche-montagne',
            'title' => 'Affiche montagne',
            'description' => 'Une affiche',
            'position' => 5,
            'isPublished' => true,
            'isDeleted' => false,
            'creation' => '2026-01-15T10:00:00+00:00',
            'categories' => [['slug' => 'affiches', 'name' => 'Affiches']],
            'relatedProducts' => [],
            'blocks' => [],
            'medias' => [[
                'name' => 'medias/shop/products/affiche-montagne-1.webp',
                'alt' => 'Affiche',
                'position' => 0,
                'updatedAt' => '2026-01-15T10:00:00+00:00',
                'file' => 'files/aaa_picture.webp',
            ]],
            'items' => [[
                'slug' => 'a3',
                'title' => 'A3',
                'description' => 'Format A3',
                'price' => 2500,
                'currency' => 'eur',
                'vat' => 20,
                'position' => 0,
                'creation' => '2026-01-15T10:00:00+00:00',
                'media' => null,
                'file' => [
                    'name' => 'medias/shop/items/affiche-montagne-a3-2.pdf',
                    'alt' => null,
                    'position' => 0,
                    'updatedAt' => '2026-01-15T10:00:00+00:00',
                    'file' => 'files/bbb_paid.pdf',
                ],
            ]],
        ];
    }

    private function createProvider(string $projectDir, ?Product $existingProduct = null): ProductImportProvider
    {
        $this->persisted = [];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findOneBySlug')->willReturn($existingProduct);

        $categoryRepository = $this->createStub(ProductCategoryRepository::class);
        $categoryRepository->method('findOneBySlug')->willReturn(null);

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findOneBy')->willReturn(null);

        return new ProductImportProvider(
            $em,
            $productRepository,
            $categoryRepository,
            $mediaRepository,
            new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class)),
            $projectDir,
        );
    }

    // @return list<object>
    private function persistedOf(string $class): array
    {
        return array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof $class));
    }

    private function createDir(): string
    {
        return sys_get_temp_dir() . '/product_import_provider_test_' . bin2hex(random_bytes(4));
    }
}
