<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Management\ProductExportProvider;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Guards the ordering the products index persists when a row is dropped (see UiBundle's ea-index-sort.js), and what a renamed or permanently deleted product leaves behind at its old url. The drag itself is a browser gesture, so what is locked here is what the payload it POSTs turns into.
class ProductCrudControllerTest extends TestCase
{
    private function createController(?RedirectRepository $redirectRepository = null): ProductCrudController
    {
        return new ProductCrudController(
            $this->createStub(AdminContextProviderInterface::class),
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(Connection::class),
            $this->createStub(ContentExporter::class),
            $this->createStub(CsrfTokenManagerInterface::class),
            $this->createStub(ProductExportProvider::class),
            $redirectRepository ?? $this->createStub(RedirectRepository::class),
            $this->createStub(RequestStack::class),
            $this->createStub(TableExporter::class),
            $this->createStub(TranslatorInterface::class),
        );
    }

    private function applyOrder(array $products, array $ids): array
    {
        return new \ReflectionMethod(ProductCrudController::class, 'applyOrder')
            ->invoke($this->createController(), $products, $ids);
    }

    // The catalogue as the reorder action reads it: ordered by position, each product carrying its id
    private function catalogue(int $count): array
    {
        $products = [];
        for ($id = 1; $id <= $count; ++$id) {
            $product = new Product()->setPosition($id - 1);
            new \ReflectionProperty(Product::class, 'id')->setValue($product, $id);
            $products[] = $product;
        }

        return $products;
    }

    public function testDroppedProductsAreRenumberedInTheSubmittedOrder(): void
    {
        $products = $this->catalogue(3);

        $positions = $this->applyOrder($products, [3, 1, 2]);

        $this->assertSame([3 => 0, 1 => 1, 2 => 2], $positions);
        $this->assertSame(1, $products[0]->getPosition());
        $this->assertSame(2, $products[1]->getPosition());
        $this->assertSame(0, $products[2]->getPosition());
    }

    // The index is paginated: a page dropped in a new order must keep its own slots rather than renumber itself from 0 over the pages before it
    public function testAProductOfAnotherPageKeepsItsPosition(): void
    {
        $products = $this->catalogue(4);

        $positions = $this->applyOrder($products, [4, 3]);

        $this->assertSame([4 => 2, 3 => 3], $positions);
        $this->assertSame(0, $products[0]->getPosition());
        $this->assertSame(1, $products[1]->getPosition());
    }

    // A tampered payload naming a product the catalogue doesn't hold reorders nothing
    public function testAnUnknownIdIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->applyOrder($this->catalogue(2), [1, 99]);
    }

    // Same refusal for a payload repeating an id, which would otherwise leave a slot filled twice and another emptied
    public function testARepeatedIdIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->applyOrder($this->catalogue(2), [1, 1]);
    }

    // A repository holding the given rows, answered by the two lookups the redirect helpers make
    private function redirectRepository(array $byFromPath = [], array $byToUrl = []): RedirectRepository
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturnCallback(static fn (string $path): ?Redirect => $byFromPath[$path] ?? null);
        $repository->method('findByToUrl')->willReturnCallback(static fn (string $url): array => $byToUrl[$url] ?? []);

        return $repository;
    }

    // The entity manager as the helpers use it, keeping what each call was handed
    private function recordingEntityManager(array &$persisted, array &$removed): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });

        return $entityManager;
    }

    private function invoke(ProductCrudController $controller, string $method, array $arguments): void
    {
        new \ReflectionMethod(ProductCrudController::class, $method)->invokeArgs($controller, $arguments);
    }

    public function testRenamingAProductSendsItsOldUrlToTheNewOne(): void
    {
        $persisted = [];
        $removed = [];
        $controller = $this->createController($this->redirectRepository());

        $this->invoke($controller, 'redirectSlugChange', [$this->recordingEntityManager($persisted, $removed), 'affiche', 'affiche-encadree']);

        $this->assertCount(1, $persisted);
        $this->assertSame('/shop/products/affiche', $persisted[0]->getFromPath());
        $this->assertSame('/shop/products/affiche-encadree', $persisted[0]->getToUrl());
        $this->assertTrue($persisted[0]->isPermanent());
    }

    // Renaming a product back to a name it already had would otherwise leave the two rows pointing at each other
    public function testRenamingBackRemovesTheRedirectThatWouldLoop(): void
    {
        $persisted = [];
        $removed = [];
        $reverse = new Redirect()->setFromPath('/shop/products/affiche')->setToUrl('/shop/products/affiche-encadree');
        $controller = $this->createController($this->redirectRepository(['/shop/products/affiche' => $reverse]));

        $this->invoke($controller, 'redirectSlugChange', [$this->recordingEntityManager($persisted, $removed), 'affiche-encadree', 'affiche']);

        $this->assertSame([$reverse], $removed);
        $this->assertSame('/shop/products/affiche-encadree', $persisted[0]->getFromPath());
        $this->assertSame('/shop/products/affiche', $persisted[0]->getToUrl());
    }

    public function testDeletingAProductForGoodLeavesA410AtItsUrl(): void
    {
        $persisted = [];
        $removed = [];
        $controller = $this->createController($this->redirectRepository());

        $this->invoke($controller, 'writeGoneRedirect', [$this->recordingEntityManager($persisted, $removed), new Product()->setSlug('affiche')]);

        $this->assertCount(1, $persisted);
        $this->assertSame('/shop/products/affiche', $persisted[0]->getFromPath());
        $this->assertTrue($persisted[0]->isGone());
        $this->assertNull($persisted[0]->getToUrl());
    }

    // A redirect an admin set up towards that product would otherwise dangle - what it led to is just as removed
    public function testDeletingAProductForGoodTurnsTheRedirectsPointingAtItIntoGoneRows(): void
    {
        $persisted = [];
        $removed = [];
        $dangling = new Redirect()->setFromPath('/affiche')->setToUrl('/shop/products/affiche');
        $controller = $this->createController($this->redirectRepository([], ['/shop/products/affiche' => [$dangling]]));

        $this->invoke($controller, 'writeGoneRedirect', [$this->recordingEntityManager($persisted, $removed), new Product()->setSlug('affiche')]);

        $this->assertTrue($dangling->isGone());
        $this->assertNull($dangling->getToUrl());
    }

    // A path an admin already covered says more than a dead end, so it is left alone
    public function testAPathAlreadyRedirectedKeepsItsOwnTarget(): void
    {
        $persisted = [];
        $removed = [];
        $existing = new Redirect()->setFromPath('/shop/products/affiche')->setToUrl('/shop/products/poster');
        $controller = $this->createController($this->redirectRepository(['/shop/products/affiche' => $existing]));

        $this->invoke($controller, 'writeGoneRedirect', [$this->recordingEntityManager($persisted, $removed), new Product()->setSlug('affiche')]);

        $this->assertSame([], $persisted);
        $this->assertFalse($existing->isGone());
        $this->assertSame('/shop/products/poster', $existing->getToUrl());
    }
}
