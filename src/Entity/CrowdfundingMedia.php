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
use c975L\ShopBundle\Entity\Crowdfunding;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingMedia extends Media
{
    #[Vich\UploadableField(mapping: 'crowdfundings', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    public function getMappingName(): string
    {
        return 'crowdfundings';
    }

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }
}
