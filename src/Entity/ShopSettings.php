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

    // What the shop's index says above its listing - composed in the back-office with UiBundle's kinds, the same way a product sheet and a category page are
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'shop_settings_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
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

    public function getId(): ?int
    {
        return $this->id;
    }
}
