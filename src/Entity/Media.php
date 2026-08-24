<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use c975L\ShopBundle\Repository\MediaRepository;
use c975L\UiBundle\Entity\Trait\VichMediaTrait;
use Doctrine\ORM\Mapping as ORM;

// Its own SINGLE_TABLE hierarchy, sharing only the trait with the other bundles' Media, never the table
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'shop_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'product' => ProductMedia::class,
    'product_item' => ProductItemMedia::class,
    'product_item_file' => ProductItemFile::class,
])]
abstract class Media
{
    use VichMediaTrait;

    // The alternative text of the picture, the one field of a product media a search engine and a screen reader both read - the trait carries what the file is, never what it shows
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    // What UiBundle's Image and Slider components read on a media besides its file, answered here rather than stored: a product picture has no caption, no credits and no css of its own to set, and duplicating the media library's columns to say "nothing" would be a schema for no content
    public function getMimeType(): string
    {
        $extension = strtolower(pathinfo((string) $this->getName(), \PATHINFO_EXTENSION));

        // Videos are named rather than prefixed: an ".ogv" file is "video/ogg", and a source element typed with a mime type that does not exist is skipped by the browser
        $videos = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg'];

        return $videos[$extension] ?? 'image/' . $extension;
    }

    public function getLabel(): ?string
    {
        return null;
    }

    // Left unset so the component prints no width/height attribute: the file is resized on upload and the stylesheet sizes it, a hardcoded pair would fight it
    public function getWidth(): ?string
    {
        return null;
    }

    public function getHeight(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getCssClasses(): array
    {
        return [];
    }

    public function isAbove(): bool
    {
        return false;
    }

    public function getCredits(): ?string
    {
        return null;
    }

    public function isRightsReserved(): bool
    {
        return false;
    }
}
