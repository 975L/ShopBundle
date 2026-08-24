<?php

namespace c975L\ShopBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class ProductItemFile extends Media implements VichPrivateFileInterface, VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(mappedBy: 'file')]
    private ?ProductItem $productItem = null;

    public function getProductItem(): ?ProductItem
    {
        return $this->productItem;
    }

    public function setProductItem(?ProductItem $productItem): static
    {
        $this->productItem = $productItem;

        return $this;
    }

    public function getPrivateDirectory(): string
    {
        return 'private';
    }

    public function getVichMediaPath(): string
    {
        return 'medias/shop/items/' . $this->getProductItem()->getProduct()->getSlug() . '-' . $this->getProductItem()->getSlug();
    }
}
