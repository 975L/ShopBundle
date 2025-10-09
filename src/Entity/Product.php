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
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use c975L\ShopBundle\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_product')]
#[UniqueEntity('slug')]
class Product
{
    private string $type = 'product';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $modification = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\OneToMany(targetEntity: ProductMedia::class, mappedBy: 'product', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $medias;

    #[ORM\OneToMany(targetEntity: ProductItem::class, mappedBy: 'product', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $items;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ProductCategory::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductCategory $category = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(ProductMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setProduct($this);
        }

        return $this;
    }

    public function removeMedia(ProductMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            // set the owning side to null (unless already changed)
            if ($media->getProduct() === $this) {
                $media->setProduct(null);
            }
        }

        return $this;
    }

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(DateTimeInterface $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(DateTimeInterface $modification): static
    {
        $this->modification = $modification;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

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

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ProductItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setProduct($this);
        }

        return $this;
    }

    public function removeItem(ProductItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getProduct() === $this) {
                $item->setProduct(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }
}
