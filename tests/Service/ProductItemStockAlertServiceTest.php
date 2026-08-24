<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use c975L\ShopBundle\Service\ProductItemStockAlertService;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Who gets told an item is back, and who does not: the waiting list only accepts what is sold out, and only sends on what a visitor could actually buy when they click
class ProductItemStockAlertServiceTest extends TestCase
{
    /** @var EmailSendRequest[] */
    private array $sent = [];

    private bool $mailerAccepts = true;

    private function createService(ProductItemStockAlertRepository $repository): ProductItemStockAlertService
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn('<p>It is back</p>');

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request): bool {
            $this->sent[] = $request;

            return $this->mailerAccepts;
        });

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key): ?string => 'shop-name' === $key ? 'La Boutique' : null
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Back in stock');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.org/x');

        return new ProductItemStockAlertService(
            $repository,
            new ProductStateService(),
            $em,
            $renderer,
            $emailService,
            $configService,
            $translator,
            $urlGenerator,
        );
    }

    private function item(?int $limited, ?int $ordered, bool $published = true): ProductItem
    {
        $product = new Product()
            ->setTitle('The book')
            ->setSlug('the-book')
            ->setIsPublished(true)
        ;

        return new ProductItem()
            ->setTitle('Paperback')
            ->setLimitedQuantity($limited)
            ->setOrderedQuantity($ordered)
            ->setIsPublished($published)
            ->setProduct($product)
        ;
    }

    private function alert(ProductItem $productItem): ProductItemStockAlert
    {
        return new ProductItemStockAlert()
            ->setProductItem($productItem)
            ->setEmail('waiting@example.org')
            ->setLocale('fr')
        ;
    }

    private function repositoryReturning(array $pending = [], ?ProductItemStockAlert $existing = null): ProductItemStockAlertRepository
    {
        $repository = $this->createStub(ProductItemStockAlertRepository::class);
        $repository->method('findPending')->willReturnCallback(
            static fn (int $limit): array => array_slice($pending, 0, $limit)
        );
        $repository->method('findOneByItemAndEmail')->willReturn($existing);

        return $repository;
    }

    public function testAnItemThatIsSoldOutAcceptsASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertTrue($service->subscribe($this->item(5, 5), 'waiting@example.org', 'fr'));
    }

    // Nothing to wait for: taking the address would promise an email that is never sent
    public function testAnItemStillInStockRefusesASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertFalse($service->subscribe($this->item(5, 2), 'waiting@example.org', 'fr'));
        $this->assertFalse($service->subscribe($this->item(null, 100), 'waiting@example.org', 'fr'));
    }

    // The distinction the whole feature turns on: 0 is withdrawn from sale, not sold out, and nothing is expected back
    public function testAnItemWithdrawnFromSaleRefusesASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertFalse($service->subscribe($this->item(0, 0), 'waiting@example.org', 'fr'));
    }

    // The unique constraint on (item, email) leaves no second row to create, so subscribing again has to put the one that exists back on the list
    public function testSubscribingAgainPutsTheExistingRowBackOnTheList(): void
    {
        $item = $this->item(5, 5);
        $existing = $this->alert($item)->setNotifiedAt(new \DateTimeImmutable('-1 month'));

        $service = $this->createService($this->repositoryReturning([], $existing));

        $this->assertTrue($service->subscribe($item, 'waiting@example.org', 'en'));
        $this->assertNull($existing->getNotifiedAt());
        $this->assertSame('en', $existing->getLocale());
    }

    public function testASubscriberIsToldOnceTheItemIsBack(): void
    {
        $alert = $this->alert($this->item(5, 2));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(1, $service->notifyPending(50));
        $this->assertNotNull($alert->getNotifiedAt());
        $this->assertCount(1, $this->sent);
        $this->assertSame('waiting@example.org', $this->sent[0]->to);
        $this->assertStringContainsString('La Boutique', (string) $this->sent[0]->subject);
        // renderNamed() has already wrapped the body: wrapping it again would nest the site's layout inside itself
        $this->assertFalse($this->sent[0]->wrapLayout);
    }

    public function testASubscriberWhoseItemIsStillOutIsLeftWaiting(): void
    {
        $alert = $this->alert($this->item(5, 5));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyPending(50));
        $this->assertNull($alert->getNotifiedAt());
        $this->assertSame([], $this->sent);
    }

    // An item back in stock but taken offline is not something the visitor could buy when they click
    public function testAnItemTakenOfflineIsNotWrittenAbout(): void
    {
        $alert = $this->alert($this->item(5, 2, published: false));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyPending(50));
        $this->assertNull($alert->getNotifiedAt());
    }

    public function testAProductTakenOfflineIsNotWrittenAbout(): void
    {
        $item = $this->item(5, 2);
        $item->getProduct()?->setIsPublished(false);
        $alert = $this->alert($item);

        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyPending(50));
    }

    // A product not on sale yet is back in stock and still unbuyable, which is what its availability date says
    public function testAProductNotYetOnSaleIsNotWrittenAbout(): void
    {
        $item = $this->item(5, 2);
        $item->getProduct()?->setAvailableAt(new \DateTime('+1 month'));
        $alert = $this->alert($item);

        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyPending(50));
    }

    // The whole point of holding the queue in the database: what the mailer refused is tried again next run rather than counted as told
    public function testASendThatFailedLeavesTheRowWaiting(): void
    {
        $this->mailerAccepts = false;
        $alert = $this->alert($this->item(5, 2));

        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyPending(50));
        $this->assertNull($alert->getNotifiedAt());
    }

    // A restocked best-seller carries thousands of subscriptions: the run sends its batch and leaves the rest for the next one
    public function testOneRunSendsNoMoreThanItsBatch(): void
    {
        $item = $this->item(5, 2);
        $pending = [];
        foreach (range(1, 10) as $ignored) {
            $pending[] = $this->alert($item);
        }

        $service = $this->createService($this->repositoryReturning($pending));

        $this->assertSame(3, $service->notifyPending(3));
        $this->assertCount(3, $this->sent);
    }
}
