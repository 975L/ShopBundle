<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

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
use c975L\ShopBundle\Entity\CrowdfundingCounterpartMedia;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents()
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

        if (!file_exists($absolutePath)) {
            return;
        }

        $extension = $entity->getFile()->getExtension();
        if (in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            // ProductMedia - CrowdfundingMedia
            if ($entity instanceof ProductMedia || $entity instanceof CrowdfundingMedia) {
                $width = 400;
            // ProductItemMedia - CrowdfundingCounterpartMedia
            } elseif ($entity instanceof ProductItemMedia || $entity instanceof CrowdfundingCounterpartMedia) {
                $width = 200;
            }

            // Resizes image file
            $format = 'webp';
            $imagine = new Imagine();
            $media = $imagine->open($absolutePath);
            $size = $media->getSize();
            $originalHeight = $size->getHeight();
            $originalWidth = $size->getWidth();

            $height = (int) ($originalHeight * $width / $originalWidth);
            $media
                ->resize(new Box($width, $height))
                ->save($absolutePath, ['format' => $format])
            ;

            // Updates entity
            if (method_exists($entity, 'setSize')) {
                $newFileSize = filesize($absolutePath);
                $entity->setSize($newFileSize);

                $this->entityManager->persist($entity);
                $this->entityManager->flush();
            }

            return;
        }

        // Moves ProductItemFile to private folder
        if ($entity instanceof ProductItemFile) {
            $filename = $entity->getName();
            if (file_exists($filename)) {
                $publicPath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;
                $privatePath = $this->parameterBag->get('kernel.project_dir') . '/private/' . $filename;

                $filesystem = new Filesystem();
                $filesystem->copy($publicPath, $privatePath, true);
                $filesystem->remove($publicPath);
            }
        }
    }
}