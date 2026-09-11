<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use c975L\ShopBundle\Repository\ShopSettingsRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// The shop's index has no entity of its own to hang its blocks on, the way a product sheet and a category page have - this is that entity, and nothing else: a single row, holding what the editor composed above the listing
#[ORM\Entity(repositoryClass: ShopSettingsRepository::class)]
#[ORM\Table(name: 'shop_settings')]
class ShopSettings implements \Stringable, HasBlocksInterface
{
    use HasBlocksTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The shop's own line, said once above everything the blocks compose below it - left empty, the page falls back to the sentence the back-office menu already uses to describe the shop link
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    // What the shop's index says above its listing - composed in the back-office with UiBundle's kinds, the same way a product sheet and a category page are
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'shop_settings_block')]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    // Never read by a visitor, the row having no name of its own: what EasyAdmin writes in its breadcrumb and its flash messages
    public function __toString(): string
    {
        return 'shop';
    }

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see ShopTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntro(): ?string
    {
        return $this->translated['intro'] ?? $this->intro;
    }

    public function setIntro(?string $intro): self
    {
        $this->intro = $intro;

        return $this;
    }

    // Lays what a language says over the texts this row was written with, for the render being built and no longer than that - only ShopTranslator calls it, and only on the front, a form screen having to go on reading the row
    /** @param array<string, string|null> $values field => value */
    public function setTranslated(array $values): void
    {
        $this->translated = $values;
    }

    // The text the row itself carries, whatever language is being rendered - what a language screen offers as the thing to translate, and what tells an untouched field from a written one (see ShopTranslator)
    public function getUntranslated(string $field): ?string
    {
        return match ($field) {
            'intro' => $this->intro,
            default => null,
        };
    }
}
