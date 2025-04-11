<?php

namespace c975L\ShopBundle\Namer;

use SplFileInfo;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Doctrine\ORM\EntityManagerInterface;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\CrowdfundingMedia;
use Vich\UploaderBundle\Naming\NamerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use c975L\ShopBundle\Entity\CrowdfundingCounterpartMedia;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ShopMediaNamer implements NamerInterface
{
    const SHOP_ROOT = 'medias/shop';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public function name($entity, PropertyMapping $mapping): string
    {
        $filePath = $entity->getFile()->getPathname();
        if (file_exists($filePath)) {
            $file = $mapping->getFile($entity);

            $mimeType = $file->getMimeType();
            $extension = $file->getExtension();
            if ('image/jpeg' === $mimeType || 'image/jpg' === $mimeType) {
                $extension = 'jpg';
            } elseif ('image/png' === $mimeType) {
                $extension = 'png';
            } elseif ('image/gif' === $mimeType) {
                $extension = 'gif';
            } elseif ('image/webp' === $mimeType) {
                $extension = 'webp';
            }

            // Changes to webp for images files
            if (in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
                $extension = 'webp';
            }

            // ProductMedia
            if ($entity instanceof ProductMedia) {
                $filename = '/products/' . $entity->getProduct()->getSlug();
            // CrowdfundingMedia
            } elseif ($entity instanceof CrowdfundingMedia) {
                $filename = '/crowdfundings/' . $entity->getCrowdfunding()->getSlug();
            // ProductItemMedia
            } elseif ($entity instanceof ProductItemMedia) {
                $filename = '/items/' . $entity->getProductItem()->getProduct()->getSlug() . '-' . $entity->getProductItem()->getSlug();
            // ProductItemFile
            } elseif ($entity instanceof ProductItemFile) {
                $filename = '/items/' . $entity->getProductItem()->getProduct()->getSlug() . '-' . $entity->getProductItem()->getSlug();
            // CrowdfundingCounterpartMedia
            } elseif ($entity instanceof CrowdfundingCounterpartMedia) {
                $filename = '/items/' . $entity->getCrowdfundingCounterpart()->getCrowdfunding()->getSlug() . '-' . $entity->getCrowdfundingCounterpart()->getSlug();
            }

            $extension = '' === $extension ? $entity->getFile()->getClientOriginalExtension() : $extension;

            return self::SHOP_ROOT . $filename . '-' . uniqid() . '.' . $extension;
        }
    }
}