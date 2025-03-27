<?php

namespace c975L\ShopBundle\Listener\Traits;

use Imagine\Image\Box;
use Imagine\Gd\Imagine;


// Defines methods related to media
trait MediaTrait
{
    private array $processedEntities = [];

    // Deletes all medias
    public function deleteMedias($entity): void
    {
        if (method_exists($entity, 'getMedias')) {
            foreach ($entity->getMedias()->toArray() as $media) {
                $this->deleteMedia($media);
           }
        }
    }

    // Delete media
    public function deleteMedia($entity): void
    {
        if (method_exists($entity, 'getName') && null !== $entity->getName()) {
            $name = __DIR__ . '/../../../public/' . $entity->getName();
            if (file_exists($name)) {
                unlink($name);
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

        if (method_exists($entity, 'getName') && null !== $entity->getName()) {



        $filePath = $entity->getFile()->getPathname();
        if (file_exists($filePath)) {
            $format = 'webp';
            $root = 'medias/shop';

            // ProductMedia
            if (method_exists($entity, 'getProduct')) {
                $height = 600;
                $root .= '/products';
                $filename = '/' . $entity->getProduct()->getSlug() . '-' . pathinfo($filePath, PATHINFO_FILENAME) . '.' . $format;
            // ProductItemMedia
            }  elseif (method_exists($entity, 'getProductItem')) {
                $height = 400;
                $root .= '/items';
                if (null === $entity->getProductItem()) {
                    $filename = '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.' . $format;
                } else {
                    $filename = '/' . $entity->getProductItem()->getProduct()->getSlug() . '-' . $entity->getProductItem()->getId() . '-' . pathinfo($filePath, PATHINFO_FILENAME) . '.' . $format;
                }
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
            $filePathSave = pathinfo($filePath, PATHINFO_DIRNAME) . $filename;
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
