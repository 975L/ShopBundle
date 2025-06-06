<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\Media;
use c975L\ShopBundle\Entity\Crowdfunding;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingVideo extends Media
{
    #[Vich\UploadableField(mapping: 'crowdfundings', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    public function getMappingName(): string
    {
        return 'crowdfundingVideos';
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

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        if (!empty($youtubeUrl)) {
            $this->setUpdatedAt(new DateTimeImmutable());
            $this->setName('YouTube (' . $youtubeUrl . ')');
        }

        return $this;
    }
}