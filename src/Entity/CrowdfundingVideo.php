<?php

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\Crowdfunding;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingVideo extends Media
{
    #[Vich\UploadableField(mapping: 'crowdfundings', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(inversedBy: 'video', cascade: ['persist', 'remove'])]
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