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
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Repository\ProductItemRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductCatalogWriter;
use c975L\UiBundle\Model\CatalogProduct;
use c975L\UiBundle\Model\CatalogProductItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// A catalog's product is created once under its key, then only its items' files and prices follow the catalog - an item it no longer lists is hidden, and a file is copied in again only when it changed
class ProductCatalogWriterTest extends TestCase
{
    private string $projectDir;

    /** @var list<array{?object, ?string}> each new item's file and slug, as they stood when it was persisted */
    private array $itemsAtPersist = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/catalog-writer-' . uniqid();
        mkdir($this->projectDir . '/private/medias', 0o777, true);
        file_put_contents($this->projectDir . '/loup.epub', 'epub v1');
        file_put_contents($this->projectDir . '/private/medias/loup.epub', 'epub v1');
    }

    protected function tearDown(): void
    {
        unlink($this->projectDir . '/private/medias/loup.epub');
        unlink($this->projectDir . '/loup.epub');
        rmdir($this->projectDir . '/private/medias');
        rmdir($this->projectDir . '/private');
        rmdir($this->projectDir);
    }

    public function testAProductMissingIsCreatedWithItsItems(): void
    {
        $persisted = [];
        $this->writer(null, $persisted)->write($this->catalogProduct());

        $product = $persisted[0];
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('book-12', $product->getCatalogKey());
        $this->assertSame('le-loup', $product->getSlug());
        $item = $product->getItems()->first();
        $this->assertSame('book-12-digital-epub', $item->getCatalogKey());
        $this->assertSame(299, $item->getPrice());
        $this->assertSame('eur', $item->getCurrency());
        $this->assertInstanceOf(ReplacingFile::class, $item->getFile()->getFile());
    }

    // Vich uploads in prePersist: the file and the slug its path is built from are there before the item is persisted
    public function testANewItemCarriesItsFileAndSlugWhenPersisted(): void
    {
        $product = new Product()->setCatalogKey('book-12')->setSlug('le-loup');

        $persisted = [];
        $this->writer($product, $persisted)->write($this->catalogProduct());

        $this->assertInstanceOf(ReplacingFile::class, $this->itemsAtPersist[0][0]);
        $this->assertSame('format-epub', $this->itemsAtPersist[0][1]);
    }

    // The item key is unique over the table: an item another product holds is moved here, not written twice
    public function testAnItemHeldByAnotherProductIsMovedRatherThanDuplicated(): void
    {
        $item = new ProductItem()->setCatalogKey('book-12-digital-epub')->setTitle('EPUB')->setFile(new ProductItemFile()->setName('medias/loup.epub'));
        new Product()->setCatalogKey('book-11')->addItem($item);
        $product = new Product()->setCatalogKey('book-12');

        $persisted = [];
        $this->writer($product, $persisted, [$item])->write($this->catalogProduct());

        $this->assertSame($product, $item->getProduct());
        $this->assertCount(1, $product->getItems());
    }

    // A new item without a price is sold at 0 and shown, its title cut to the column's 50 characters
    public function testANewItemWithoutPriceIsWrittenAtZeroWithItsTitleCut(): void
    {
        $product = new Product()->setCatalogKey('book-12')->setSlug('le-loup');
        $title = str_repeat('Le Loup ', 10);

        $persisted = [];
        $this->writer($product, $persisted)->write(new CatalogProduct('book-12', 'Le Loup', '<p>Un loup.</p>', [
            new CatalogProductItem('book-12-digital-epub', $title, $this->projectDir . '/loup.epub'),
        ]));

        $item = $product->getItems()->first();
        $this->assertSame(0, $item->getPrice());
        $this->assertFalse($item->isHidden());
        $this->assertSame(50, mb_strlen($item->getTitle()));
        $this->assertSame($title, $item->getDescription());
    }

    // The editor's choice to hide an item survives the next write
    public function testAnItemTheEditorHidStaysHidden(): void
    {
        $item = new ProductItem()->setCatalogKey('book-12-digital-epub')->setTitle('EPUB')->setHidden(true)->setFile(new ProductItemFile()->setName('medias/loup.epub'));
        $product = new Product()->setCatalogKey('book-12')->addItem($item);

        $persisted = [];
        $this->writer($product, $persisted)->write($this->catalogProduct());

        $this->assertTrue($item->isHidden());
    }

    // A key another row already carries is left to it, the other key still stamped
    public function testSetKeysLeavesAKeyTakenElsewhere(): void
    {
        $item = new ProductItem()->setTitle('EPUB');
        $product = new Product()->addItem($item);
        $other = new Product()->setCatalogKey('book-12');

        $persisted = [];
        $this->writer($other, $persisted, [], $item)->setKeys(7, 'book-12', 'book-12-digital-epub');

        $this->assertNull($product->getCatalogKey());
        $this->assertSame('book-12-digital-epub', $item->getCatalogKey());
    }

    public function testAnExistingProductKeepsItsTextAndHidesWhatTheCatalogDropped(): void
    {
        $product = new Product()->setCatalogKey('book-12')->setTitle('Titre du shop');
        $kept = new ProductItem()->setCatalogKey('book-12-digital-epub')->setTitle('EPUB')->setPrice(500)->setFile(new ProductItemFile()->setName('medias/loup.epub'));
        $dropped = new ProductItem()->setCatalogKey('book-12-digital-pdf')->setTitle('PDF');
        $ownItem = new ProductItem()->setTitle('Livre imprimé');
        $product->addItem($kept)->addItem($dropped)->addItem($ownItem);

        $persisted = [];
        $this->writer($product, $persisted)->write($this->catalogProduct());

        $this->assertSame([], $persisted);
        $this->assertSame('Titre du shop', $product->getTitle());
        $this->assertSame(299, $kept->getPrice());
        // The same content: no copy
        $this->assertNull($kept->getFile()->getFile());
        $this->assertTrue($dropped->isHidden());
        $this->assertFalse($ownItem->isHidden());
    }

    // A file of the same size changed since it was copied is copied in again
    public function testAFileChangedSinceItWasCopiedIsCopiedAgain(): void
    {
        touch($this->projectDir . '/loup.epub', time() + 60);
        $item = new ProductItem()->setCatalogKey('book-12-digital-epub')->setTitle('EPUB')->setFile(new ProductItemFile()->setName('medias/loup.epub'));
        $product = new Product()->setCatalogKey('book-12')->addItem($item);

        $persisted = [];
        $this->writer($product, $persisted)->write($this->catalogProduct());

        $this->assertInstanceOf(ReplacingFile::class, $item->getFile()->getFile());
    }

    private function catalogProduct(): CatalogProduct
    {
        return new CatalogProduct('book-12', 'Le Loup', '<p>Un loup.</p>', [
            new CatalogProductItem('book-12-digital-epub', 'Format EPUB', $this->projectDir . '/loup.epub', 299),
        ]);
    }

    /**
     * @param list<object>      $persisted
     * @param list<ProductItem> $otherItems items held by other products, found by their key too
     */
    private function writer(?Product $existing, array &$persisted, array $otherItems = [], ?ProductItem $found = null): ProductCatalogWriter
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Product => isset($criteria['catalogKey']) ? $existing : null);

        $items = $existing instanceof Product ? [...$existing->getItems()->toArray(), ...$otherItems] : $otherItems;

        return new ProductCatalogWriter($this->entityManager($persisted), $productRepository, $this->itemRepository($items, $found), new AsciiSlugger(), $this->projectDir);
    }

    /** @param list<ProductItem> $items */
    private function itemRepository(array $items, ?ProductItem $found): ProductItemRepository
    {
        $repository = $this->createStub(ProductItemRepository::class);
        $repository->method('find')->willReturn($found);
        $repository->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($items): ?ProductItem {
            foreach ($items as $item) {
                if ($criteria['catalogKey'] === $item->getCatalogKey()) {
                    return $item;
                }
            }

            return null;
        });

        return $repository;
    }

    // Records the products persisted, and each new item's file and slug as they stood at that moment
    private function entityManager(array &$persisted): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            if ($entity instanceof Product) {
                $persisted[] = $entity;
            }
            if ($entity instanceof ProductItem) {
                $this->itemsAtPersist[] = [$entity->getFile()?->getFile(), $entity->getSlug()];
            }
        });

        return $entityManager;
    }
}
