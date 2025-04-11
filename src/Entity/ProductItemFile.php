<?php

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\ProductItem;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class ProductItemFile extends Media
{
    #[Vich\UploadableField(mapping: 'productItems', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(mappedBy: 'file', cascade: ['persist'])]
    private ?ProductItem $productItem = null;

    public function getMappingName(): string
    {
        return 'productItems';
    }

    public function getProductItem(): ?ProductItem
    {
        return $this->productItem;
    }

    public function setProductItem(?ProductItem $productItem): static
    {
        $this->productItem = $productItem;

        return $this;
    }
}