<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\Trait\TrashBatchDeleteTrait;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Management\ProductExportProvider;
use c975L\ShopBundle\Service\ProductSocialContentSource;
use c975L\ShopBundle\Service\ShopTranslator;
use c975L\UiBundle\Contract\SocialContentStatusProviderInterface;
use c975L\UiBundle\Repository\FavoriteRepository;
use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Guards the ordering the products index persists when a row is dropped (see UiBundle's ea-index-sort.js), and what a renamed or permanently deleted product leaves behind at its old url. The drag itself is a browser gesture, so what is locked here is what the payload it POSTs turns into.
class ProductCrudControllerTest extends TestCase
{
    private function createController(?RedirectRepository $redirectRepository = null, ?ContentLocaleScreen $contentLocaleScreen = null, ?ShopTranslator $shopTranslator = null, ?RequestStack $requestStack = null): ProductCrudController
    {
        return new ProductCrudController(
            $this->createStub(AdminContextProviderInterface::class),
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $this->configService(),
            $this->createStub(Connection::class),
            $this->createStub(ContentExporter::class),
            $contentLocaleScreen ?? $this->createStub(ContentLocaleScreen::class),
            $this->createStub(CsrfTokenManagerInterface::class),
            $this->createStub(FavoriteRepository::class),
            $this->createStub(ProductExportProvider::class),
            $this->createStub(RatingRepository::class),
            $redirectRepository ?? $this->createStub(RedirectRepository::class),
            $requestStack ?? $this->createStub(RequestStack::class),
            $this->createStub(ReviewRepository::class),
            $shopTranslator ?? $this->createStub(ShopTranslator::class),
            $this->createStub(TableExporter::class),
            $this->createStub(TranslatorInterface::class),
        );
    }

    // A product holding one saved variant (id 12) and one not saved yet, which has nothing to file a translation under
    private function productWithItems(): Product
    {
        $saved = new ProductItem()->setTitle('Large')->setDescription('Blue');
        new \ReflectionProperty(ProductItem::class, 'id')->setValue($saved, 12);

        return new Product()
            ->addItem($saved)
            ->addItem(new ProductItem()->setTitle('Small')->setDescription('Red'));
    }

    // The language screen offers each saved variant its own name and description, holding what promptValues() hands back
    public function testTheLanguageScreenOffersEachSavedVariantItsOwnTexts(): void
    {
        $shopTranslator = $this->createStub(ShopTranslator::class);
        $shopTranslator->method('promptValues')->willReturn(['title' => '[Large]', 'description' => '[Blue]']);
        $controller = $this->createController(shopTranslator: $shopTranslator);

        $fields = new \ReflectionMethod(ProductCrudController::class, 'itemTranslationFields')
            ->invoke($controller, $this->productWithItems(), 'en');

        $dtos = array_map(static fn (FieldInterface $field) => $field->getAsDto(), $fields);
        $inputs = array_values(array_filter($dtos, static fn ($dto): bool => !str_starts_with((string) $dto->getProperty(), 'ea_form_')));

        $this->assertCount(3, $fields, 'One fieldset and two fields, for the saved variant alone');
        $this->assertSame(['item_12_title', 'item_12_description'], array_map(static fn ($dto) => $dto->getProperty(), $inputs));
        $this->assertSame('[Large]', $inputs[0]->getFormTypeOption('data'));
        $this->assertFalse($inputs[1]->getFormTypeOption('mapped'));
    }

    // A submission stages each variant's texts under the variant, and a field the form does not carry is left out rather than staged as null, which would erase it
    public function testASubmissionStagesTheVariantTextsUnderTheVariant(): void
    {
        $staged = [];
        $shopTranslator = $this->createStub(ShopTranslator::class);
        $shopTranslator->method('stage')->willReturnCallback(static function (object $row, string $locale, array $values) use (&$staged): void {
            $staged[] = [$row, $locale, $values];
        });
        $contentLocaleScreen = new ContentLocaleScreen(new RequestStack(), $this->createStub(AdminUrlGeneratorInterface::class), $this->createStub(SiteLocales::class));
        $controller = $this->createController(contentLocaleScreen: $contentLocaleScreen, shopTranslator: $shopTranslator);

        $product = $this->productWithItems();
        $builder = Forms::createFormFactory()
            ->createBuilder(FormType::class, $product, ['data_class' => Product::class])
            ->add('item_12_title', TextType::class, ['mapped' => false]);

        new \ReflectionMethod(ProductCrudController::class, 'stageItemsOnSubmit')->invoke($controller, $builder, $product, 'en');
        $builder->getForm()->submit(['item_12_title' => 'Grand']);

        $this->assertCount(1, $staged);
        $this->assertSame($product->getItems()->first(), $staged[0][0]);
        $this->assertSame('en', $staged[0][1]);
        $this->assertSame(['title' => 'Grand'], $staged[0][2]);
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

    // A controller deleting from the catalogue or from the bin, the screen read from the query as the batch posts it, with a session for the flash it leaves
    /** @return array{ProductCrudController, Session} */
    private function deletingController(bool $trash, bool $admin = true): array
    {
        $request = new Request($trash ? ['trash' => '1'] : []);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack([$request]);
        $controller = $this->createController($this->redirectRepository(), requestStack: $requestStack);
        $container = new Container();
        $container->set('request_stack', $requestStack);
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($admin);
        $container->set('security.authorization_checker', $authorizationChecker);
        $controller->setContainer($container);

        return [$controller, $session];
    }

    // Deleting a product of the catalogue, one by one or in a batch, only moves it to the recycle bin
    public function testDeletingALiveProductFromTheCatalogueMovesItToTheBin(): void
    {
        $persisted = [];
        $removed = [];
        $product = new Product()->setSlug('affiche');
        [$controller] = $this->deletingController(false);

        $controller->deleteEntity($this->recordingEntityManager($persisted, $removed), $product);

        $this->assertTrue($product->isDeleted());
        $this->assertSame([], $removed);
        $this->assertSame([], $persisted);
    }

    // Deleting a product from the bin, which is what its batch action does, removes it for good and leaves a 410 at its url - the message for the whole selection is TrashBatchDeleteTrait's, said once rather than per row
    public function testDeletingATrashedProductFromTheBinRemovesItForGood(): void
    {
        $persisted = [];
        $removed = [];
        $product = new Product()->setSlug('affiche')->setIsDeleted(true);
        [$controller, $session] = $this->deletingController(true);

        $controller->deleteEntity($this->recordingEntityManager($persisted, $removed), $product);

        $this->assertSame([$product], $removed);
        $this->assertSame('/shop/products/affiche', $persisted[0]->getFromPath());
        $this->assertTrue($persisted[0]->isGone());
        $this->assertSame([], $session->getFlashBag()->peek('success'));
    }

    // The settings the controller reads, the admin role among them - a role left null would not even build the screen
    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): ?string => 'site-role-admin' === $key ? 'ROLE_ADMIN' : null);

        return $configService;
    }

    // The bin is titled as such, with the key every bundle's bin uses, the catalogue by its plural
    public function testTheBinIsTitledAsSuch(): void
    {
        [$trash] = $this->deletingController(true);
        [$catalogue] = $this->deletingController(false);

        $this->assertSame('action.trash', $trash->configureCrud(Crud::new())->getAsDto()->getCustomPageTitle(Crud::PAGE_INDEX)?->getMessage());
        $this->assertSame('label.products', $catalogue->configureCrud(Crud::new())->getAsDto()->getCustomPageTitle(Crud::PAGE_INDEX)?->getMessage());
    }

    // Removing for good is the admin's, EasyAdmin's single delete held to the bar of the row button
    public function testAnEditorCannotRemoveAProductForGood(): void
    {
        $persisted = [];
        $removed = [];
        [$controller] = $this->deletingController(true, admin: false);

        try {
            $controller->deleteEntity($this->recordingEntityManager($persisted, $removed), new Product()->setSlug('affiche')->setIsDeleted(true));
            $this->fail('An editor removed a product for good');
        } catch (AccessDeniedException) {
            $this->assertSame([], $removed);
        }
    }

    // The bin's batch goes through ConfigBundle's trait, which checks the admin role before the first row and says once how many went
    public function testTheBinBatchIsTrashBatchDeleteTrait(): void
    {
        $this->assertContains(TrashBatchDeleteTrait::class, class_uses(ProductCrudController::class));
    }

    // A replayed "Move to the recycle bin" on a product already there, or a stale bin selection holding one restored meanwhile, is left alone
    public function testDeletingFromTheOtherScreenLeavesTheProductAlone(): void
    {
        foreach ([[false, true], [true, false]] as [$trash, $deleted]) {
            $persisted = [];
            $removed = [];
            $product = new Product()->setSlug('affiche')->setIsDeleted($deleted);
            [$controller] = $this->deletingController($trash);

            $controller->deleteEntity($this->recordingEntityManager($persisted, $removed), $product);

            $this->assertSame($deleted, $product->isDeleted());
            $this->assertSame([], $removed);
            $this->assertSame([], $persisted);
        }
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

    // The shared social column (see UiBundle's SocialStatusCrudTrait), asked about under the type SocialBundle files the products under, its keys shipped in every locale of this bundle's domain
    public function testTheSocialColumnIsTheProductsOne(): void
    {
        $controller = new \ReflectionClass(ProductCrudController::class)->newInstanceWithoutConstructor();
        foreach (['socialStatuses' => $this->createStub(SocialContentStatusProviderInterface::class), 'requestStack' => new RequestStack([new Request()])] as $property => $value) {
            new \ReflectionProperty(ProductCrudController::class, $property)->setValue($controller, $value);
        }

        $label = new \ReflectionMethod(ProductCrudController::class, 'socialStatusFields')->invoke($controller)[0]->getAsDto()->getLabel();

        $this->assertSame('label.product_social', $label->getMessage());
        $this->assertSame('shop', $label->getDomain());
        $this->assertSame(ProductSocialContentSource::SOURCE_TYPE, new \ReflectionMethod(ProductCrudController::class, 'socialSourceType')->invoke($controller));
        foreach (['en', 'fr', 'es'] as $locale) {
            $catalogue = (string) file_get_contents(dirname(__DIR__, 3) . '/translations/shop.' . $locale . '.xlf');
            foreach (['', '_date_format', '_reserved', '_published'] as $suffix) {
                $this->assertStringContainsString('<source>label.product_social' . $suffix . '</source>', $catalogue, $locale);
            }
        }
    }

    // Only the products the shop lists are asked about: a hidden or a scheduled one is left without a badge
    public function testOnlyListedProductsAreAskedAboutTheirPost(): void
    {
        $controller = new \ReflectionClass(ProductCrudController::class)->newInstanceWithoutConstructor();
        $shown = new \ReflectionMethod(ProductCrudController::class, 'socialStatusRowShown');

        $this->assertTrue($shown->invoke($controller, new Product()->setHidden(false)));
        $this->assertTrue($shown->invoke($controller, new Product()->setHidden(false)->setAvailableAt(new \DateTime('-1 day'))));
        $this->assertFalse($shown->invoke($controller, new Product()->setHidden(true)));
        $this->assertFalse($shown->invoke($controller, new Product()->setHidden(false)->setAvailableAt(new \DateTime('+1 day'))));
    }

    // The social column is on the catalogue only: no post holds a trashed product nor a template
    public function testTheSocialColumnIsOnTheCatalogueOnly(): void
    {
        $fields = static function (array $query): array {
            $requestStack = new RequestStack([new Request($query)]);
            $controller = new \ReflectionClass(ProductCrudController::class)->newInstanceWithoutConstructor();
            foreach (['socialStatuses' => self::createStub(SocialContentStatusProviderInterface::class), 'requestStack' => $requestStack] as $property => $value) {
                new \ReflectionProperty(ProductCrudController::class, $property)->setValue($controller, $value);
            }

            return new \ReflectionMethod(ProductCrudController::class, 'socialStatusFields')->invoke($controller);
        };

        $this->assertCount(1, $fields([]));
        $this->assertSame([], $fields(['trash' => '1']));
        $this->assertSame([], $fields(['templates' => '1']));
    }
}
