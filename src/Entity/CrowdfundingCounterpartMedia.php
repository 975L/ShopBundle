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
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingCounterpartMedia extends Media
{
    #[Vich\UploadableField(mapping: 'crowdfundingsCounterparts', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(inversedBy: 'media')]
    private ?CrowdfundingCounterpart $crowdfundingCounterpart = null;

    public function getMappingName(): string
    {
        return 'crowdfundingsCounterparts';
    }

    public function getCrowdfundingCounterpart(): ?CrowdfundingCounterpart
    {
        return $this->crowdfundingCounterpart;
    }

    public function setCrowdfundingCounterpart(?CrowdfundingCounterpart $crowdfundingCounterpart): static
    {
        $this->crowdfundingCounterpart = $crowdfundingCounterpart;

        return $this;
    }
}
