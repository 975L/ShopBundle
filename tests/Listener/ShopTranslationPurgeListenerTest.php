<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Listener;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ShopSettings;
use c975L\ShopBundle\Listener\ShopTranslationPurgeListener;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

// Translations name their owner rather than pointing at it, so no foreign key takes them along - a new row landing on a deleted one's id would otherwise inherit its translated name
class ShopTranslationPurgeListenerTest extends TestCase
{
    /** @var list<array{string, int}> */
    private array $deleted;

    protected function setUp(): void
    {
        $this->deleted = [];
    }

    // Each of the four translatable rows is purged under the owner name ShopTranslator files it as, the id read on preRemove since Doctrine has nulled it by postRemove
    public function testEachTranslatableRowIsPurgedUnderItsOwnOwnerName(): void
    {
        $rows = [
            [new Product(), ShopTranslator::OWNER_PRODUCT],
            [new ProductCategory(), ShopTranslator::OWNER_CATEGORY],
            [new ProductItem(), ShopTranslator::OWNER_ITEM],
            [new ShopSettings(), ShopTranslator::OWNER_SETTINGS],
        ];

        foreach ($rows as [$entity, $owner]) {
            $this->deleted = [];
            $listener = $this->listener();
            $listener->preRemove($this->preEvent($this->withId($entity, 7)));
            $this->assertSame([], $this->deleted, 'Nothing is deleted before the flush removes the row');

            $listener->postRemove($this->postEvent($this->withId($entity, null)));

            $this->assertSame([[$owner, 7]], $this->deleted);
        }
    }

    // A row of another bundle passing through the very same events is none of this listener's business
    public function testARowOfAnotherKindIsLeftAlone(): void
    {
        $listener = $this->listener();
        $block = new Block();
        $listener->preRemove($this->preEvent($block));
        $listener->postRemove($this->postEvent($block));

        $this->assertSame([], $this->deleted);
    }

    // Nothing was ever stored under a row that never had an identifier, so there is nothing to delete
    public function testARowWithoutAnIdentifierDeletesNothing(): void
    {
        $listener = $this->listener();
        $product = new Product();
        $listener->preRemove($this->preEvent($product));
        $listener->postRemove($this->postEvent($product));

        $this->assertSame([], $this->deleted);
    }

    // postRemove alone has no id left to go on, whatever the row still says
    public function testPostRemoveWithoutPreRemoveDeletesNothing(): void
    {
        $this->listener()->postRemove($this->postEvent($this->withId(new Product(), 7)));

        $this->assertSame([], $this->deleted);
    }

    // A row removed twice deletes once: the pending entry is taken on the way out
    public function testThePendingEntryIsConsumedOnce(): void
    {
        $listener = $this->listener();
        $product = $this->withId(new Product(), 7);
        $listener->preRemove($this->preEvent($product));
        $listener->postRemove($this->postEvent($product));
        $listener->postRemove($this->postEvent($product));

        $this->assertSame([[ShopTranslator::OWNER_PRODUCT, 7]], $this->deleted);
    }

    private function listener(): ShopTranslationPurgeListener
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('deleteByOwner')->willReturnCallback(
            function (string $owner, int $id): int {
                $this->deleted[] = [$owner, $id];

                return 1;
            }
        );

        return new ShopTranslationPurgeListener($repository);
    }

    private function preEvent(object $entity): PreRemoveEventArgs
    {
        return new PreRemoveEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function postEvent(object $entity): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    // setId() is not offered by these entities, the identifier being Doctrine's own - written straight through reflection to reproduce a row that has been saved, or one Doctrine has just deleted
    private function withId(object $entity, ?int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }
}
