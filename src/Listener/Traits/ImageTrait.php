<?php

namespace c975L\ShopBundle\Listener\Traits;

use c975L\ShopBundle\Entity\ProductMedia;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Doctrine\ORM\EntityManagerInterface;

// Defines methods related to image
trait ImageTrait
{
    private array $processedEntities = [];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    // Deletes all images
    public function deleteImages($entity): void
    {
        foreach ($entity->getMedias()->toArray() as $productMedia) {
            $this->deleteImage($productMedia);
        }
    }

    // Delete image
    public function deleteImage($entity): void
    {
        $name = __DIR__ . '/../../../public/' . $entity->getName();
        if (file_exists($name)) {
            unlink($name);
        }
    }

    // Resizes image
    public function resizeImage(ProductMedia $entity): void
    {
        // Checks if entity has already been processed
        $entityId = $entity->getId();
        if ($entityId && in_array($entityId, $this->processedEntities)) {
            return;
        }

        $filePath = $entity->getFile()->getPathname();
        if (file_exists($filePath)) {
            $height = 600;
            $format = 'webp';
            $root = 'medias/shop/products';

            // Gets image
            $imagine = new Imagine();
            $image = $imagine->open($filePath);
            $size = $image->getSize();
            $originalWidth = $size->getWidth();
            $originalHeight = $size->getHeight();

            // Saves file
            $width = (int) (($height / $originalHeight) * $originalWidth);
            $image->resize(new Box($width, $height))->save($filePath);
            $filename = '/' . $entity->getProduct()->getSlug() . '-' . pathinfo($filePath, PATHINFO_FILENAME) . '.' . $format;
            $filePathSave = pathinfo($filePath, PATHINFO_DIRNAME) . $filename;
            $image->save($filePathSave, ['format' => $format]);

            // Updates entity and deletes original file
            $entity->setName($root . $filename);
            unlink($filePath);

            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }
    }
}
