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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Management\ShopFilesHealthCheckProvider;
use c975L\ShopBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShopFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/shop-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // Each row is a media and the project-relative directory its file sits in, null for a file left off the disk
    /** @param array<int, array{0: Media, 1: ?string}> $rows */
    private function createProvider(array $rows): ShopFilesHealthCheckProvider
    {
        $medias = [];
        foreach ($rows as [$media, $directory]) {
            if (null !== $directory) {
                $path = $this->projectDir . '/' . $directory . '/' . $media->getName();
                new Filesystem()->mkdir(\dirname($path));
                file_put_contents($path, 'file');
            }

            $medias[] = $media;
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findWithFilename')->willReturn($medias);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/product/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new ShopFilesHealthCheckProvider(
            $mediaRepository,
            $adminUrlGenerator,
            $configService,
            $translator,
            $this->projectDir,
        );
    }

    private function createProduct(int $id = 1): Product
    {
        $product = new Product()->setTitle('Les Triados');
        new \ReflectionProperty(Product::class, 'id')->setValue($product, $id);

        return $product;
    }

    private function createProductMedia(string $filename): ProductMedia
    {
        return new ProductMedia()->setName($filename)->setProduct($this->createProduct());
    }

    // A digital item: uploaded under public/ like any other, then moved to private/ by VichImageResizeListener, its row still naming the path it had
    private function createItemFile(string $filename): ProductItemFile
    {
        $item = new ProductItem()->setProduct($this->createProduct());
        $file = new ProductItemFile()->setName($filename);
        $file->setProductItem($item);

        return $file;
    }

    public function testGetKind(): void
    {
        $this->assertSame('files-shop', $this->createProvider([])->getKind());
    }

    public function testAShopDeclaringNoFileReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    public function testADeclaredFileMissingFromTheServerIsAnError(): void
    {
        $rows = $this->createProvider([[$this->createProductMedia('medias/shop/triados.webp'), null]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/medias/shop/triados.webp', $rows[0]['url']);
        $this->assertSame('Les Triados / media', $rows[0]['label']);
        $this->assertSame('/management/product/edit', $rows[0]['editUrl']);
    }

    public function testAFileInPlaceStillGetsItsRow(): void
    {
        $rows = $this->createProvider([[$this->createProductMedia('medias/shop/triados.webp'), 'public']])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // The whole reason this bundle needed the check: a digital item is what a buyer is sent a link to, and it does not live under public/ at all
    public function testADigitalItemIsLookedForOutsidePublic(): void
    {
        $rows = $this->createProvider([[$this->createItemFile('medias/shop/items/triados-pdf.pdf'), 'private']])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('Les Triados / file', $rows[0]['label']);
    }

    // The same file sitting under public/ is not the one that gets delivered: the row has to stay red rather than pass on a leftover
    public function testADigitalItemLeftUnderPublicIsStillAnError(): void
    {
        $rows = $this->createProvider([[$this->createItemFile('medias/shop/items/triados-pdf.pdf'), 'public']])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
    }

    // An orphan media names no product: the row falls back to the filename and leads nowhere rather than to a broken edit screen
    public function testAMediaHangingOffNoProductIsNamedByItsFilename(): void
    {
        $rows = $this->createProvider([[new ProductMedia()->setName('medias/shop/orphan.webp'), null]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('medias/shop/orphan.webp', $rows[0]['label']);
        $this->assertNull($rows[0]['editUrl']);
    }
}
