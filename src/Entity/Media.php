<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\LotteryVideo;
use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Entity\CrowdfundingMedia;
use c975L\ShopBundle\Entity\CrowdfundingVideo;
use Symfony\Component\HttpFoundation\File\File;
use c975L\ShopBundle\Repository\MediaRepository;
use c975L\ShopBundle\Entity\CrowdfundingCounterpartMedia;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'shop_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'product' => ProductMedia::class,
    'product_item' => ProductItemMedia::class,
    'product_item_file' => ProductItemFile::class,
    'crowdfunding' => CrowdfundingMedia::class,
    'crowdfunding_counterpart' => CrowdfundingCounterpartMedia::class,
    'crowdfunding_video' => CrowdfundingVideo::class,
    'lottery_video' => LotteryVideo::class,
])]

abstract class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    protected ?File $file = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne()]
    private ?User $user = null;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    // Critical for preventing duplicates - overrides default Doctrine behavior
    public function equals(object $other): bool
    {
        if (!$other instanceof Media) {
            return false;
        }

        // If both entities have IDs, compare by ID
        if ($this->getId() !== null && $other->getId() !== null) {
            return $this->getId() === $other->getId();
        }

        // If one doesn't have an ID but both have names, compare by name
        if ($this->getName() && $other->getName()) {
            return $this->getName() === $other->getName();
        }

        // Otherwise, they're not equal
        return false;
    }

    abstract public function getMappingName(): string;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        if ($file) {
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
