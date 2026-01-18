<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\ProductItem;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class ProductItemMedia extends Media
{
    #[Vich\UploadableField(mapping: 'productItems', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(mappedBy: 'media', cascade: ['persist', 'remove'])]
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
