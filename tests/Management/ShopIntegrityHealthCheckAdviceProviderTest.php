<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\PaymentBundle\Controller\Management\BasketCrudController;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Management\ShopIntegrityHealthCheckAdviceProvider;
use c975L\ShopBundle\Management\ShopIntegrityHealthCheckProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A count says there is something to fix, a link is what gets it fixed
class ShopIntegrityHealthCheckAdviceProviderTest extends TestCase
{
    /** @var list<string> */
    private array $controllers = [];

    public function testAnOffendingArticleLinksToItsProductSheet(): void
    {
        $advice = $this->buildAdvice([['productId' => 3, 'label' => 'A book / An item', 'info' => 'price 0']]);
        $line = $advice['shop-integrity|https://example.com/#free-items'][0];

        $this->assertCount(1, $line['items']);
        $this->assertSame('label.health_check_advice_shop_offender', $line['items'][0]['text']);
        $this->assertSame([ProductCrudController::class], $this->controllers);
    }

    // A delivery that never happened is looked at on the order, which is where the buyer and their files are
    public function testAnUndeliveredOrderLinksToTheOrder(): void
    {
        $this->buildAdvice([['basketId' => 42, 'label' => '2026-000042', 'info' => '1 file(s)']]);

        $this->assertSame([BasketCrudController::class], $this->controllers);
    }

    public function testAGreenRowCarriesNoAdvice(): void
    {
        $this->assertSame([], $this->buildAdvice([]));
    }

    // Every kind's results are handed to every advice provider, this one answering only for its own
    public function testAnotherKindIsLeftAlone(): void
    {
        $result = new HealthCheckResult()
            ->setKind('product-json-ld')
            ->setUrl('https://example.com/')
            ->setDetails(['offenders' => [['productId' => 3, 'label' => 'A book', 'info' => '']]])
        ;

        $this->assertSame([], $this->provider()->buildAdvice([$result]));
    }

    private function buildAdvice(array $offenders): array
    {
        $result = new HealthCheckResult()
            ->setKind(ShopIntegrityHealthCheckProvider::KIND)
            ->setUrl('https://example.com/' . ShopIntegrityHealthCheckProvider::ROW_FREE_ITEMS)
            ->setDetails(['offenders' => $offenders])
        ;

        return $this->provider()->buildAdvice([$result]);
    }

    private function provider(): ShopIntegrityHealthCheckAdviceProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new ShopIntegrityHealthCheckAdviceProvider($this->adminUrlGenerator(), $translator);
    }

    // Records the CRUD controller each link is generated for, an advice line keeping only the url it came back with
    private function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/generated');
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator): AdminUrlGeneratorInterface {
            $this->controllers[] = $controller;

            return $generator;
        });

        return $generator;
    }
}
