<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Traits;

use SplFileInfo;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

// Defines methods related to media
trait MediaTrait
{
    private array $processedEntities = [];

    const MEDIA_ROOT = __DIR__ . '/../../../public/';
    const SHOP_ROOT = 'medias/shop';

    // Initializes the slugger
    public function initializeMedia(SluggerInterface $slugger): void
    {
        $this->slugger = $slugger;
    }

    // Deletes all medias
    public function deleteMedias($entity): void
    {
        if (method_exists($entity, 'getMedias')) {
            foreach ($entity->getMedias()->toArray() as $media) {
                $this->deleteMedia($media);
           }
        }
    }

    // Delete media (if media is not deleted, use MediaDeleteCommand)
    public function deleteMedia($entity): void
    {
        if (method_exists($entity, 'getName') && method_exists($entity, 'getFile') && null !== $entity->getFile()) {
            $name = __DIR__ . self::MEDIA_ROOT . $entity->getName();
            if (file_exists($name)) {
                unlink($name);
            }
        }
    }

    // Renames the item file
    public function renameItemFile($entity): void
    {
        if (method_exists($entity, 'getFile') && null !== $entity->getFile()) {
            $filePath = $entity->getFile()->getPathname();
            if (file_exists($filePath)) {
                $fileInfo = new SplFileInfo($filePath);
                $filename = self::SHOP_ROOT . '/items/' ;
                $filename .= $entity->getProductItem()->getProduct()->getSlug() . '-';
                $filename .= $entity->getProductItem()->getSlug() . '-';
                $filename .= $fileInfo->getBasename('.' . $fileInfo->getExtension()) . '.' . $fileInfo->getExtension();

                $filesystem = new Filesystem();
                $filesystem->copy($filePath, '../private/' . $filename);
                $filesystem->remove($filePath);

                $entity->setName($filename);

                $this->entityManager->persist($entity);
                $this->entityManager->flush();
            }
        }
    }

    // Resizes media (ProdutMedia | ProductItemMedia)
    public function resizeMedia($entity): void
    {
        // Checks if entity has already been processed
        $entityId = $entity->getId();
        if ($entityId && in_array($entityId, $this->processedEntities) || null === $entity->getFile()) {
            return;
        }

        if (method_exists($entity, 'getFile') && null !== $entity->getFile()) {
            $filePath = $entity->getFile()->getPathname();
            if (file_exists($filePath)) {
                $format = 'webp';
                $fileInfo = new SplFileInfo($filePath);
                $filenameWithoutExt = $fileInfo->getBasename('.' . $fileInfo->getExtension());

                // ProductMedia
                if (method_exists($entity, 'getProduct')) {
                    $height = 600;
                    $root = self::SHOP_ROOT . '/products';
                    $filename = '/' . $entity->getProduct()->getSlug() . '-' . $filenameWithoutExt . '.' . $format;
                // ProductItemMedia
                } elseif (method_exists($entity, 'getProductItem')) {
                    $height = 400;
                    $root = self::SHOP_ROOT . '/items';
                    $filename = '/' . $entity->getProductItem()->getProduct()->getSlug() . '-' . $entity->getProductItem()->getId() . '-' . $filenameWithoutExt . '.' . $format;
                }

                // Gets media
                $imagine = new Imagine();
                $media = $imagine->open($filePath);
                $size = $media->getSize();
                $originalWidth = $size->getWidth();
                $originalHeight = $size->getHeight();

                // Saves file
                $width = (int) (($height / $originalHeight) * $originalWidth);
                $media->resize(new Box($width, $height))->save($filePath);
                $filePathSave = dirname($filePath) . $filename;
                $media->save($filePathSave, ['format' => $format]);

                // Updates entity and deletes original file
                $entity->setName($root . $filename);
                unlink($filePath);

                $this->entityManager->persist($entity);
                $this->entityManager->flush();
            }

        }
    }
}
