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
use c975L\ShopBundle\Entity\Lottery;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class LotteryVideo extends Media
{
    #[Vich\UploadableField(mapping: 'crowdfundings', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Lottery::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Lottery $lottery = null;

    public function getMappingName(): string
    {
        return 'lotteryVideos';
    }

    public function getLottery(): ?Lottery
    {
        return $this->lottery;
    }

    public function setLottery(?Lottery $lottery): static
    {
        $this->lottery = $lottery;

        return $this;
    }
}