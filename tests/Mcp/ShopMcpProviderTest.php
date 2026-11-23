<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Mcp;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductCategory;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Mcp\ShopCategoryTools;
use c975L\ShopBundle\Mcp\ShopMcpAccess;
use c975L\ShopBundle\Mcp\ShopMcpProvider;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductStateServiceInterface;
use c975L\ShopBundle\Service\ProductUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// What an agent may read and write of the catalog, with the back office's rights and constraints
class ShopMcpProviderTest extends TestCase
{
    // The tools named under the "shop" key, the categories' joined, the writing ones flagged
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('shop', $this->provider()->getMcpKey());
        $this->assertSame(['shop_list', 'shop_get', 'shop_create', 'shop_edit', 'shop_category_list', 'shop_category_get', 'shop_category_create', 'shop_category_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true, false, false, true, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
    }

    // The agent is told an edit of what is already visible goes live at once, in the instructions and on both edit tools
    public function testLiveEditsAreSaid(): void
    {
        $tools = array_column(array_map(static fn ($tool): array => [$tool->name, $tool->description], iterator_to_array($this->provider()->getMcpTools(), false)), 1, 0);

        $this->assertStringContainsString('An edit of a product or category already visible shows on the site at once.', $this->provider()->getMcpInstructions());
        $this->assertStringContainsString('shows on the site at once', $tools['shop_edit']);
        $this->assertStringContainsString('shows on the site at once', $tools['shop_category_edit']);
    }

    // A product added hidden, without item, with a slug kept unique
    public function testCreateHidden(): void
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Product => 'item-1' === $criteria['slug'] ? new Product() : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('item-1-2', $this->provider($repository, $entityManager)->create('Item 1', ' description-1 ')['slug']);
        $this->assertInstanceOf(Product::class, $persisted[0]);
        $this->assertTrue($persisted[0]->isHidden());
        $this->assertCount(0, $persisted[0]->getItems());
        $this->assertSame('description-1', $persisted[0]->getDescription());
    }

    // A title made of signs only still gives a slug
    public function testCreateWithoutSluggableTitle(): void
    {
        $this->assertSame('product', $this->provider()->create('!!!', 'description-1')['slug']);
    }

    // A product the back office's form would refuse is refused, nothing persisted
    public function testCreateRefusedByTheConstraints(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('description: blank');
        $this->provider(entityManager: $entityManager, violation: 'description')->create('item-1', ' ');
    }

    // Only the fields given change, saved through the back office's own update
    public function testEdit(): void
    {
        $product = new Product()->setTitle('item-1')->setSlug('item-1')->setDescription('description-1');
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->once())->method('update')->with($product);

        $this->assertSame('item-2', $this->provider($repository, updater: $updater)->edit(1, title: 'Item 2', slug: 'Item 2')['slug']);
        $this->assertSame(['Item 2', 'description-1'], [$product->getTitle(), $product->getDescription()]);
    }

    // An edit the constraints refuse is taken back off the row and never saved
    public function testEditRefusedByTheConstraints(): void
    {
        $product = new Product()->setTitle('item-1')->setSlug('item-1')->setDescription('description-1');
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('refresh')->with($product);
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(ToolCallException::class);
        $this->provider($repository, $entityManager, violation: 'description')->edit(1, description: ' ');
    }

    // A call naming no field is refused rather than re-dating the product
    public function testEditRefusesNothingToChange(): void
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn(new Product()->setSlug('item-1'));
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->never())->method('update');

        $this->expectException(ToolCallException::class);
        $this->provider($repository, updater: $updater)->edit(1);
    }

    // The categories given replace the product's own, as the back office's field does
    public function testEditFilesTheProductUnderTheCategoriesGiven(): void
    {
        $kept = $this->category(1, 'catalog');
        $added = $this->category(2, 'item-1');
        $product = new Product()->setTitle('item-1')->setSlug('item-1')->addCategory($kept)->addCategory($this->category(3, 'item-2'));
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('findBy')->willReturn([$kept, $added]);

        $this->assertSame([['id' => 1, 'name' => 'catalog'], ['id' => 2, 'name' => 'item-1']], $this->provider($repository, categories: $categories)->edit(1, categories: [1, 2, 2])['categories']);
    }

    // An unknown category refuses the whole edit before anything is changed
    public function testEditRefusesAnUnknownCategory(): void
    {
        $product = new Product()->setTitle('item-1')->setSlug('item-1');
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('findBy')->willReturn([$this->category(1, 'catalog')]);
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->never())->method('update');

        try {
            $this->provider($repository, updater: $updater, categories: $categories)->edit(1, title: 'item-2', categories: [1, 9]);
            $this->fail('An unknown category was accepted.');
        } catch (ToolCallException $exception) {
            $this->assertSame('No category 9.', $exception->getMessage());
        }
        $this->assertSame('item-1', $product->getTitle());
        $this->assertCount(0, $product->getCategories());
    }

    // An empty set takes the product out of every category
    public function testEditTakesTheProductOutOfEveryCategory(): void
    {
        $product = new Product()->setTitle('item-1')->setSlug('item-1')->addCategory($this->category(1, 'catalog'));
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);

        $this->assertSame([], $this->provider($repository)->edit(1, categories: [])['categories']);
    }

    // A slug made of signs only is refused
    public function testEditRefusesAnEmptySlug(): void
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn(new Product()->setSlug('item-1'));

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->edit(1, slug: '!!!');
    }

    // A product's items read with their price and stock, in the shop's currency
    public function testGet(): void
    {
        $item = new ProductItem()->setTitle('item-1')->setPrice(1200)->setLimitedQuantity(5)->setOrderedQuantity(2);
        $product = new Product()->setTitle('catalog')->setSlug('catalog')->setDescription('description-1');
        $product->addItem($item);
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn($product);

        $found = $this->provider($repository)->get(1);
        $this->assertSame('EUR', $found['currency']);
        $this->assertSame([1200, 5, 2], [$found['items'][0]['price'], $found['items'][0]['stock'], $found['items'][0]['ordered']]);
    }

    // A template answers as unknown, as a product in the trash does
    public function testTemplateUnknown(): void
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('find')->willReturn(new Product()->setTemplate(true));

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->get(1);
    }

    // An account that does not manage the catalog is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->list();
    }

    // A category saved under its id
    private function category(int $id, string $name): ProductCategory
    {
        $category = new ProductCategory()->setName($name)->setSlug($name);
        new \ReflectionProperty(ProductCategory::class, 'id')->setValue($category, $id);

        return $category;
    }

    // The provider for an account managing the catalog or not, its constraints passing or refusing one field
    private function provider(?ProductRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true, ?ProductUpdater $updater = null, ?string $violation = null, ?ProductCategoryRepository $categories = null): ShopMcpProvider
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        $state = $this->createStub(ProductStateServiceInterface::class);
        $state->method('getCurrency')->willReturn('EUR');
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList(null === $violation ? [] : [new ConstraintViolation('blank', null, [], null, $violation, null)]));

        $entityManager ??= $this->createStub(EntityManagerInterface::class);
        $repository ??= $this->createStub(ProductRepository::class);
        $categories ??= $this->createStub(ProductCategoryRepository::class);
        $updater ??= $this->createStub(ProductUpdater::class);
        $access = new ShopMcpAccess($config, $security, $entityManager, new AsciiSlugger(), $validator);

        return new ShopMcpProvider($repository, $state, $categories, $entityManager, $updater, $access, new ShopCategoryTools($categories, $repository, $entityManager, $updater, $access));
    }
}
