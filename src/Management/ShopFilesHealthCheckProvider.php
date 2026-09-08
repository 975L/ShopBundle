<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Controller\Management\ProductCrudController;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Repository\MediaRepository;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Management\AbstractDeclaredFilesHealthCheckProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The files this bundle's own rows declare: the pictures of a product and of its items, and the digital item files themselves. Everything the check does is in the parent (see UiBundle's AbstractDeclaredFilesHealthCheckProvider), this only names what to look for - the digital files are the ones this was written for, being what a buyer is sent a link to after paying, so a missing one is a sale that cannot be delivered
class ShopFilesHealthCheckProvider extends AbstractDeclaredFilesHealthCheckProvider
{
    // Named here rather than restated as a literal wherever a row of this kind is picked out
    public const string KIND = 'files-shop';

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    protected function declaredFiles(): iterable
    {
        foreach ($this->mediaRepository->findWithFilename() as $media) {
            yield [
                'filename' => (string) $media->getName(),
                'label' => $this->label($media),
                'editUrl' => $this->editUrl($media),
                // A digital item is moved out of public/ once uploaded (see VichImageResizeListener), its row still naming the path it had there
                'directory' => $media instanceof VichPrivateFileInterface ? $media->getPrivateDirectory() : self::PUBLIC_DIRECTORY,
            ];
        }
    }

    // The product is what names the row: the medias themselves carry no title, and several products hold a picture called the same thing
    private function label(Media $media): string
    {
        $product = $this->productOf($media);
        $name = null === $product ? '' : (string) $product->getTitle();
        $kind = $media instanceof ProductItemFile ? 'file' : 'media';

        return '' === $name ? (string) $media->getName() : $name . ' / ' . $kind;
    }

    // Every media of this bundle is re-uploaded from its product's own screen, whether it hangs off the product or off one of its items
    private function editUrl(Media $media): ?string
    {
        $product = $this->productOf($media);
        $id = $product?->getId();

        return null === $id ? null : $this->adminUrlGenerator
            ->unsetAll()
            ->setController(ProductCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($id)
            ->generateUrl()
        ;
    }

    private function productOf(Media $media): ?Product
    {
        if ($media instanceof ProductMedia) {
            return $media->getProduct();
        }

        if ($media instanceof ProductItemMedia || $media instanceof ProductItemFile) {
            return $media->getProductItem()?->getProduct();
        }

        return null;
    }
}
