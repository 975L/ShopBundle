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
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Management\ProductExportProvider;
use c975L\ShopBundle\Management\ProductImportProvider;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Management\BlockDataExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ProductExportProviderTest extends TestCase
{
    public function testGetKindMatchesProductImportProvider(): void
    {
        $provider = new ProductExportProvider($this->createStub(ProductRepository::class), new BlockDataExporter(sys_get_temp_dir()), sys_get_temp_dir());

        $this->assertSame(ProductImportProvider::KIND, $provider->getKind());
    }

    // findBy() rather than findAll(), which the repository overrides with the catalogue the public may see - a sync carries the drafts and the recycle bin too
    public function testExportAllReadsTheWholeCatalogueDraftsAndTrashIncluded(): void
    {
        $product = $this->createProduct();

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())->method('findBy')->with([], ['position' => 'ASC'])->willReturn([$product]);

        $data = new ProductExportProvider($productRepository, new BlockDataExporter(sys_get_temp_dir()), sys_get_temp_dir())->exportAll();

        $this->assertCount(1, $data['items']);
        $this->assertSame('affiche-montagne', $data['items'][0]['slug']);
        $this->assertSame([], $data['files']);
    }

    public function testSerializeCarriesTheProductWithItsCategoriesAndRelatedProducts(): void
    {
        $product = $this->createProduct();
        $product->addCategory(new ProductCategory()->setSlug('affiches')->setName('Affiches'));
        $product->addRelatedProduct($this->createProduct()->setSlug('cadre-bois'));

        $item = new ProductExportProvider($this->createStub(ProductRepository::class), new BlockDataExporter(sys_get_temp_dir()), sys_get_temp_dir())
            ->serialize([$product])['items'][0];

        $this->assertSame([['slug' => 'affiches', 'name' => 'Affiches']], $item['categories']);
        $this->assertSame(['cadre-bois'], $item['relatedProducts']);
        $this->assertFalse($item['isDeleted']);
        $this->assertTrue($item['isPublished']);
        $this->assertSame('2026-01-15T10:00:00+00:00', $item['creation']);
    }

    // The pictures under public/ and the paid file under private/, each registered as a real zip entry beside the metadata that names it
    public function testSerializeRegistersPublicPicturesAndPrivateItemFiles(): void
    {
        $projectDir = $this->createProjectDir([
            'public/medias/shop/products/affiche-montagne-1.webp' => 'picture-bytes',
            'public/medias/shop/items/affiche-montagne-a3-1.webp' => 'item-picture-bytes',
            'private/medias/shop/items/affiche-montagne-a3-2.pdf' => 'paid-bytes',
        ]);

        $product = $this->createProduct();
        $product->addMedia(new ProductMedia()->setName('medias/shop/products/affiche-montagne-1.webp')->setAlt('Affiche')->setPosition(0));

        $item = new ProductItem()->setTitle('A3')->setSlug('a3')->setDescription('Format A3')->setPrice(2500)->setCurrency('eur')->setVat(20);
        $item->setMedia(new ProductItemMedia()->setName('medias/shop/items/affiche-montagne-a3-1.webp')->setPosition(0));
        $item->setFile(new ProductItemFile()->setName('medias/shop/items/affiche-montagne-a3-2.pdf')->setPosition(0));
        $product->addItem($item);

        $data = new ProductExportProvider($this->createStub(ProductRepository::class), new BlockDataExporter($projectDir), $projectDir)->serialize([$product]);
        $exported = $data['items'][0];

        $this->assertSame('medias/shop/products/affiche-montagne-1.webp', $exported['medias'][0]['name']);
        $this->assertSame('Affiche', $exported['medias'][0]['alt']);
        $this->assertSame('medias/shop/items/affiche-montagne-a3-1.webp', $exported['items'][0]['media']['name']);
        $this->assertSame('medias/shop/items/affiche-montagne-a3-2.pdf', $exported['items'][0]['file']['name']);
        $this->assertSame(2500, $exported['items'][0]['price']);
        $this->assertTrue($exported['items'][0]['isPublished']);

        $files = array_values($data['files']);
        sort($files);
        $this->assertSame([
            $projectDir . '/private/medias/shop/items/affiche-montagne-a3-2.pdf',
            $projectDir . '/public/medias/shop/items/affiche-montagne-a3-1.webp',
            $projectDir . '/public/medias/shop/products/affiche-montagne-1.webp',
        ], $files);

        new Filesystem()->remove($projectDir);
    }

    // A placeholder holding no file, and a name whose file has left the disk, are exported as "nothing" rather than as a reference the archive doesn't carry
    public function testSerializeSkipsMediasWithoutBytesOnDisk(): void
    {
        $projectDir = $this->createProjectDir([]);

        $product = $this->createProduct();
        $product->addMedia(new ProductMedia()->setName('medias/shop/products/gone.webp'));

        $item = new ProductItem()->setTitle('A3')->setSlug('a3')->setDescription('Format A3')->setPrice(2500);
        $item->setMedia(new ProductItemMedia());
        $item->setFile(new ProductItemFile());
        $product->addItem($item);

        $exported = new ProductExportProvider($this->createStub(ProductRepository::class), new BlockDataExporter($projectDir), $projectDir)
            ->serialize([$product])['items'][0];

        $this->assertSame([], $exported['medias']);
        $this->assertNull($exported['items'][0]['media']);
        $this->assertNull($exported['items'][0]['file']);

        new Filesystem()->remove($projectDir);
    }

    private function createProduct(): Product
    {
        return new Product()
            ->setSlug('affiche-montagne')
            ->setTitle('Affiche montagne')
            ->setDescription('Une affiche')
            ->setPosition(5)
            ->setIsPublished(true)
            ->setCreation(new \DateTime('2026-01-15 10:00:00', new \DateTimeZone('UTC')))
            ->setModification(new \DateTime('2026-01-15 10:00:00', new \DateTimeZone('UTC')));
    }

    // @param array<string, string> $files
    private function createProjectDir(array $files): string
    {
        $projectDir = sys_get_temp_dir() . '/product_export_provider_test_' . bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        foreach ($files as $path => $content) {
            $filesystem->dumpFile($projectDir . '/' . $path, $content);
        }
        $filesystem->mkdir($projectDir);

        return $projectDir;
    }
}
