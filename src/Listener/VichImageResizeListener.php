<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use SplFileInfo;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Entity\ProductItemFile;
use Symfony\Component\Filesystem\Filesystem;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\CrowdfundingMedia;
use c975L\ShopBundle\Entity\CrowdfundingVideo;
use c975L\ShopBundle\Entity\CrowdfundingCounterpartMedia;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener implements EventSubscriberInterface
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->filesystem = new Filesystem();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::POST_UPLOAD => 'onPostUpload',
        ];
    }

    public function onPostUpload(Event $event)
    {
        $entity = $event->getObject();
        $mapping = $event->getMapping();
        $filename = $mapping->getFileName($entity);
        $absolutePath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;

        if (!$this->filesystem->exists($absolutePath)) {
            return;
        }

        $extension = $entity->getFile()->getExtension();

        // Process images
        if (in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            $this->processImage($entity, $absolutePath);

            return;
        }

        // Process private files
        if ($entity instanceof ProductItemFile) {
            $this->moveFileToPrivate($entity);
        }
    }

    // Resize and save the image
    private function processImage($entity, string $absolutePath): void
    {
        // Gets the width for the entity
        $width = $this->getWidthForEntity($entity);

        // Resizes the image
        $format = 'webp';
        $imagine = new Imagine();
        $media = $imagine->open($absolutePath);
        $size = $media->getSize();
        $height = (int) ($size->getHeight() * $width / $size->getWidth());

        $media
            ->resize(new Box($width, $height))
            ->save($absolutePath, [
                'format' => $format,
                'webp_quality' => 90,
            ]);

        $this->updateEntitySize($entity, $absolutePath);
    }

    // Gets the width for the entity
    private function getWidthForEntity($entity): int
    {
        if ($entity instanceof ProductItemMedia || $entity instanceof CrowdfundingCounterpartMedia) {
            return 300;
        }

        return 600;
    }

    // Updates the size of the entity
    private function updateEntitySize($entity, $filePath): void
    {
        if (method_exists($entity, 'setSize')) {
            $file = new SplFileInfo($filePath);
            $entity->setSize($file->getSize());
        }
    }

    // Moves the file to the private directory
    private function moveFileToPrivate(ProductItemFile $entity): void
    {
        $filename = $entity->getName();

        if (!$this->filesystem->exists($filename)) {
            return;
        }

        $publicPath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;
        $privatePath = $this->parameterBag->get('kernel.project_dir') . '/private/' . $filename;

        // Ensure the private directory exists
        $this->filesystem->mkdir(dirname($privatePath), 0755);

        // Copy the file to the private directory and remove the public one
        $this->filesystem->copy($publicPath, $privatePath, true);
        $this->filesystem->remove($publicPath);
    }
}