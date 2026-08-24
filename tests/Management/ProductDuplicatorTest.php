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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Management\ProductDuplicator;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media as BlockMedia;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

// Guards what a copy of a product carries: the files are copied on the disk rather than shared with the original, which deleting one product would take from the other, and what points back at the product itself is repointed at the copy
class ProductDuplicatorTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/shop-duplicator-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    private function createDuplicator(): ProductDuplicator
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('(copie)');

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        return new ProductDuplicator(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ProductRepository::class),
            new AsciiSlugger(),
            $translator,
            $parameterBag,
        );
    }

    // Writes a file where a media says it holds one
    private function writeFile(string $name, string $directory = 'public'): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/' . $directory . '/' . $name, 'content');
    }

    private function absolutePath(?string $name, string $directory = 'public'): string
    {
        return $this->projectDir . '/' . $directory . '/' . $name;
    }

    // A product as the back-office leaves it: a picture, an item with its own picture and its downloadable file, and a sheet composed of blocks
    private function createProduct(): Product
    {
        $product = new Product()
            ->setTitle('Mon produit')
            ->setSlug('mon-produit')
            ->setDescription('Sa description')
        ;

        $media = new ProductMedia()
            ->setAlt('Le produit')
            ->setPosition(0)
            ->setUpdatedAt(new \DateTimeImmutable())
        ;
        $product->addMedia($media);
        $media->setName('medias/shop/products/mon-produit-aaa.webp');
        $this->writeFile($media->getName());

        $item = new ProductItem()
            ->setTitle('Version PDF')
            ->setSlug('version-pdf')
            ->setDescription('Sa description')
            ->setPrice(1500)
            ->setCurrency('eur')
            ->setVat(0.2)
            ->setLimitedQuantity(10)
            ->setOrderedQuantity(4)
            ->setPosition(0)
        ;
        $product->addItem($item);

        $itemMedia = new ProductItemMedia()->setUpdatedAt(new \DateTimeImmutable());
        $item->setMedia($itemMedia);
        $itemMedia->setName('medias/shop/items/mon-produit-version-pdf-bbb.webp');
        $this->writeFile($itemMedia->getName());

        $itemFile = new ProductItemFile()->setUpdatedAt(new \DateTimeImmutable());
        $item->setFile($itemFile);
        $itemFile->setName('medias/shop/items/mon-produit-version-pdf-ccc.pdf');
        $this->writeFile($itemFile->getName(), 'private');

        $product->addBlock(new Block()
            ->setKind('shop_product_items')
            ->setPosition(0)
            ->setData(['productSlug' => 'mon-produit']));

        $container = new Block()->setKind('flex_columns')->setPosition(1)->setData([]);
        $slot = new Block()->setKind('document')->setPosition(0)->setData(['productSlug' => 'un-autre-produit']);
        $slotMedia = new BlockMedia()->setUpdatedAt(new \DateTimeImmutable());
        $slot->addMedia($slotMedia);
        $slotMedia->setFilename('medias/site/block-document-12-ddd.pdf');
        $this->writeFile($slotMedia->getFilename());
        $this->writeFile('medias/site/block-document-12-ddd.webp');
        $container->addSlot($slot);
        $product->addBlock($container);

        return $product;
    }

    public function testTheCopyIsNamedAfterTheOriginalAndTakesASlugOfItsOwn(): void
    {
        $copy = $this->createDuplicator()->duplicate($this->createProduct());

        $this->assertSame('Mon produit (copie)', $copy->getTitle());
        $this->assertSame('mon-produit-copie', $copy->getSlug());
        $this->assertSame('Sa description', $copy->getDescription());
    }

    // Nothing else says a copy is meant to be sold as it stands, and a catalogue with no draft state would have put it online the moment it was written
    public function testTheCopyIsADraft(): void
    {
        $product = $this->createProduct()->setIsPublished(true);

        $copy = $this->createDuplicator()->duplicate($product);

        $this->assertFalse($copy->isPublished());
        $this->assertFalse($copy->isDeleted());
    }

    public function testTheItemsAreCopiedWithNothingSoldOfThem(): void
    {
        $copy = $this->createDuplicator()->duplicate($this->createProduct());

        $this->assertCount(1, $copy->getItems());
        $item = $copy->getItems()->first();
        $this->assertSame('Version PDF', $item->getTitle());
        $this->assertSame(1500, $item->getPrice());
        $this->assertSame(10, $item->getLimitedQuantity());
        $this->assertNull($item->getOrderedQuantity());
    }

    public function testAnItemLeftOfflineStaysOfflineInTheCopy(): void
    {
        $product = $this->createProduct();
        $product->getItems()->first()->setIsPublished(false);

        $copy = $this->createDuplicator()->duplicate($product);

        $this->assertFalse($copy->getItems()->first()->isPublished());
    }

    // The brand and the offer travel with the copy, which is the same product priced the same way
    public function testTheCopyKeepsTheBrandAndThePreviousPrice(): void
    {
        $product = $this->createProduct()->setBrand('Éditions Lolant');
        $product->getItems()->first()->setPriceBefore(2000);

        $copy = $this->createDuplicator()->duplicate($product);

        $this->assertSame('Éditions Lolant', $copy->getBrand());
        $this->assertSame(2000, $copy->getItems()->first()->getPriceBefore());
    }

    // A reference and a barcode name one item and one only: two offers publishing the same GTIN is the very claim the column exists to prevent
    public function testTheCopyTakesNeitherTheReferenceNorTheBarcode(): void
    {
        $product = $this->createProduct();
        $product->getItems()->first()->setSku('AFF-A2-001')->setGtin('3760123456789');

        $item = $this->createDuplicator()->duplicate($product)->getItems()->first();

        $this->assertNull($item->getSku());
        $this->assertNull($item->getGtin());
    }

    // The relation is one-way, so the copy points at the same products without anything being written on them
    public function testTheCopyPointsAtTheSameRelatedProducts(): void
    {
        $related = new Product()->setTitle('Un autre produit')->setSlug('un-autre-produit');
        $product = $this->createProduct()->addRelatedProduct($related);

        $copy = $this->createDuplicator()->duplicate($product);

        $this->assertCount(1, $copy->getRelatedProducts());
        $this->assertSame($related, $copy->getRelatedProducts()->first());
    }

    public function testEachFileIsCopiedUnderANameOfItsOwnAndTheOriginalIsLeftAlone(): void
    {
        $product = $this->createProduct();

        $copy = $this->createDuplicator()->duplicate($product);

        $media = $copy->getMedias()->first();
        $this->assertNotSame($product->getMedias()->first()->getName(), $media->getName());
        $this->assertFileExists($this->absolutePath($media->getName()));
        $this->assertFileExists($this->absolutePath($product->getMedias()->first()->getName()));
        $this->assertStringStartsWith('medias/shop/products/mon-produit-copie-', $media->getName());

        $item = $copy->getItems()->first();
        $this->assertFileExists($this->absolutePath($item->getMedia()->getName()));
        $this->assertFileExists($this->absolutePath($item->getFile()->getName(), 'private'));
        $this->assertStringStartsWith('medias/shop/items/mon-produit-copie-version-pdf-', $item->getFile()->getName());
    }

    public function testTheBlocksOfTheSheetAreCopiedWithTheirSlotsAndTheirMedias(): void
    {
        $copy = $this->createDuplicator()->duplicate($this->createProduct());

        $this->assertCount(2, $copy->getBlocks());

        // The block selling the product it sits on now sells the copy, the one naming another product goes on naming it
        $items = $copy->getBlocks()->first();
        $this->assertSame('shop_product_items', $items->getKind());
        $this->assertSame('mon-produit-copie', $items->getData()['productSlug']);

        $slot = $copy->getBlocks()->last()->getSlots()->first();
        $this->assertSame('document', $slot->getKind());
        $this->assertSame('un-autre-produit', $slot->getData()['productSlug']);

        // A public pdf is shown through the webp of its first page, which no host redraws on a copy
        $filename = $slot->getMedias()->first()->getFilename();
        $this->assertFileExists($this->absolutePath($filename));
        $this->assertFileExists($this->absolutePath(str_replace('.pdf', '.webp', $filename)));
    }

    // A media whose file never made it to the disk, or an item left without one, must not stop the copy
    public function testAMissingFileLeavesTheCopyWithoutOne(): void
    {
        $product = $this->createProduct();
        $this->filesystem->remove($this->absolutePath($product->getMedias()->first()->getName()));

        $copy = $this->createDuplicator()->duplicate($product);

        $this->assertNull($copy->getMedias()->first()->getName());
    }
}
