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
use c975L\ShopBundle\Mcp\ShopCategoryTools;
use c975L\ShopBundle\Mcp\ShopMcpAccess;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// What an agent may read and write of the product categories, with the back office's rights and constraints
class ShopCategoryToolsTest extends TestCase
{
    // The categories read with their products counted, the listed ones told apart
    public function testList(): void
    {
        $listed = $this->category(1, 'catalog');
        $empty = $this->category(2, 'item-1');
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('findWithProductCount')->willReturn([[0 => $listed, 'products' => '3'], [0 => $empty, 'products' => 0]]);
        $products = $this->createStub(ProductRepository::class);
        $products->method('findListedCategories')->willReturn([$listed]);

        $this->assertSame([
            ['id' => 1, 'name' => 'catalog', 'slug' => 'catalog', 'listed' => true, 'products' => 3],
            ['id' => 2, 'name' => 'item-1', 'slug' => 'item-1', 'listed' => false, 'products' => 0],
        ], $this->tools($categories, $products)->list());
    }

    // A category read with its products, the trash and the templates left aside
    public function testGet(): void
    {
        $category = $this->category(1, 'catalog')->setDescription('description-1');
        $category->addProduct(new Product()->setTitle('item-1')->setHidden(true));
        $category->addProduct(new Product()->setTitle('item-2')->setIsDeleted(true));
        $category->addProduct(new Product()->setTitle('item-3')->setTemplate(true));
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('find')->willReturn($category);

        $found = $this->tools($categories)->get(1);

        $this->assertSame(['description-1', false], [$found['description'], $found['listed']]);
        $this->assertSame([['id' => null, 'title' => 'item-1', 'hidden' => true]], $found['products']);
    }

    // An unknown id answers as unknown
    public function testUnknownCategory(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('No category 9.');
        $this->tools()->get(9);
    }

    // An account that does not manage the catalog is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $tools = $this->tools(granted: false);
        foreach ([static fn () => $tools->list(), static fn () => $tools->get(1), static fn () => $tools->create('catalog'), static fn () => $tools->edit(1, name: 'catalog')] as $call) {
            try {
                $call();
                $this->fail('A tool answered an account without the role.');
            } catch (ToolCallException $exception) {
                $this->assertSame('This account may not work on the shop\'s catalog.', $exception->getMessage());
            }
        }
    }

    // A category added empty, so listed nowhere, its slug kept unique and an empty description stored as none
    public function testCreateEmpty(): void
    {
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?ProductCategory => 'catalog' === $criteria['slug'] ? new ProductCategory() : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('catalog-2', $this->tools($categories, entityManager: $entityManager)->create(' Catalog ', ' ')['slug']);
        $this->assertInstanceOf(ProductCategory::class, $persisted[0]);
        $this->assertSame(['Catalog', null], [$persisted[0]->getName(), $persisted[0]->getDescription()]);
        $this->assertCount(0, $persisted[0]->getProducts());
    }

    // A name made of signs only still gives a slug
    public function testCreateWithoutSluggableName(): void
    {
        $this->assertSame('category', $this->tools()->create('!!!')['slug']);
    }

    // A category the back office's form would refuse is refused, nothing persisted
    public function testCreateRefusedByTheConstraints(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('name: blank');
        $this->tools(entityManager: $entityManager, violation: 'name')->create(' ');
    }

    // Only the fields given change, a new slug saved through the back office's own update, which redirects the old page
    public function testEditRedirectsANewSlug(): void
    {
        $category = $this->category(1, 'catalog')->setDescription('description-1');
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('find')->willReturn($category);
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->once())->method('updateCategory')->with($category);

        $this->assertSame('catalog-2', $this->tools($categories, updater: $updater)->edit(1, name: 'Catalog 2', slug: 'Catalog 2')['slug']);
        $this->assertSame(['Catalog 2', 'description-1'], [$category->getName(), $category->getDescription()]);
    }

    // An edit the constraints refuse is taken back off the row and never saved
    public function testEditRefusedByTheConstraints(): void
    {
        $category = $this->category(1, 'catalog');
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('find')->willReturn($category);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('refresh')->with($category);
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(ToolCallException::class);
        $this->tools($categories, entityManager: $entityManager, violation: 'name')->edit(1, name: ' ');
    }

    // A call naming no field is refused
    public function testEditRefusesNothingToChange(): void
    {
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('find')->willReturn($this->category(1, 'catalog'));
        $updater = $this->createMock(ProductUpdater::class);
        $updater->expects($this->never())->method('updateCategory');

        $this->expectException(ToolCallException::class);
        $this->tools($categories, updater: $updater)->edit(1);
    }

    // A slug made of signs only is refused
    public function testEditRefusesAnEmptySlug(): void
    {
        $categories = $this->createStub(ProductCategoryRepository::class);
        $categories->method('find')->willReturn($this->category(1, 'catalog'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('The slug is empty.');
        $this->tools($categories)->edit(1, slug: '!!!');
    }

    // A category saved under its id
    private function category(int $id, string $name): ProductCategory
    {
        $category = new ProductCategory()->setName($name)->setSlug($name);
        new \ReflectionProperty(ProductCategory::class, 'id')->setValue($category, $id);

        return $category;
    }

    // The tools for an account managing the catalog or not, its constraints passing or refusing one field
    private function tools(?ProductCategoryRepository $categories = null, ?ProductRepository $products = null, ?EntityManagerInterface $entityManager = null, bool $granted = true, ?ProductUpdater $updater = null, ?string $violation = null): ShopCategoryTools
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList(null === $violation ? [] : [new ConstraintViolation('blank', null, [], null, $violation, null)]));
        $entityManager ??= $this->createStub(EntityManagerInterface::class);

        return new ShopCategoryTools(
            $categories ?? $this->createStub(ProductCategoryRepository::class),
            $products ?? $this->createStub(ProductRepository::class),
            $entityManager,
            $updater ?? $this->createStub(ProductUpdater::class),
            new ShopMcpAccess($config, $security, $entityManager, new AsciiSlugger(), $validator),
        );
    }
}
