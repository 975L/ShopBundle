<?php

namespace c975L\ShopBundle\Entity;

use c975L\ShopBundle\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(length: 5)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $isNumeric = null;

    #[ORM\Column]
    private ?float $vat = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'products')]
    private Collection $medias;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
    }

    // Converts the entity in an array
    public function toArray()
    {
        $product = get_object_vars($this);
        unset($product['medias']);
        $product['image'] = $this->getMediasNames()[0];
        $product['vatAmount'] = $this->getVatAmount();

        return $product;
    }

    // Returns the VAT amount
    public function getVatAmount(): float
    {
        return $this->getVat() > 0 ? round($this->getPrice() / 100 - ($this->getPrice() / (100 + $this->getVat())), 2) : 0;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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

    public function isNumeric(): ?bool
    {
        return $this->isNumeric;
    }

    public function setNumeric(bool $isNumeric): static
    {
        $this->isNumeric = $isNumeric;

        return $this;
    }

    public function getVat(): ?float
    {
        return $this->vat;
    }

    public function setVat(float $vat): static
    {
        $this->vat = $vat;

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    // Returns array of medias names, including path
    public function getMediasNames(): array
    {
        $mediaNames = [];
        foreach ($this->medias as $media) {
            $mediaNames[] = '/shop/' . $media->getName();
        }

        return $mediaNames;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setProducts($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->medias->removeElement($media)) {
            // set the owning side to null (unless already changed)
            if ($media->getProducts() === $this) {
                $media->setProducts(null);
            }
        }

        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(\DateTimeInterface $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(\DateTimeInterface $modification): static
    {
        $this->modification = $modification;

        return $this;
    }
}
