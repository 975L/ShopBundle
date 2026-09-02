<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ShopBundle\Repository\ProductItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductItemRepository::class)]
#[ORM\Table(name: 'shop_product_item')]
class ProductItem implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // Required on the entity and not only on the form: the back-office submits without the browser's own check, and the slug is written off this title when the row is saved (see ProductItemListener)
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    // Required on the entity like the title above, and for the same reason: the column is NOT NULL, and a form submitted without it fails as a 500 rather than as a field the back-office can point at
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    // NotNull and not NotBlank: an item given away costs 0, which the integrity check lists as a free item rather than as an error
    #[ORM\Column]
    #[Assert\NotNull]
    private ?int $price = null;

    // What the item was sold for before the current price, struck through beside it - null when it is not on offer. Read by ProductStateService, which ignores anything not above the price, so a leftover value never publishes a discount of zero or less
    #[ORM\Column(nullable: true)]
    private ?int $priceBefore = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'eur';

    // The shop's own reference for this item, published as the offer's sku - the slug stands in for it when it is left empty, which is what the graph did for every item before this column existed
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sku = null;

    // The item's barcode number, 8 to 14 digits - an EAN-13 for a shelf product, an ISBN-13 for a book. Left empty on anything made in-house, which carries none and must not claim one
    #[ORM\Column(length: 14, nullable: true)]
    private ?string $gtin = null;

    #[ORM\Column]
    private float $vat = 0;

    // Three states in one column: null is an unlimited stock, a cap the orders have not reached is what is left to sell, a cap they have reached is a shortage the shop expects to end, and 0 is an item withdrawn for good. Null by default, so a shop publishing without touching the field is not read as having withdrawn everything
    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $limitedQuantity = null;

    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $orderedQuantity = null;

    #[ORM\Column(nullable: true)]
    private ?bool $service = null;

    // Whether the item is set aside, one hidden keeping its file and its stock rather than being deleted - no recycle bin beside it, unlike Product: what has no url of its own has no address to answer 410 for
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $hidden = false;

    // What a card bought through this item is worth, in cents, null on everything that is not a gift card - stated here rather than read off the price, a 50 € card being free to sell for 45
    #[ORM\Column(nullable: true)]
    private ?int $giftCardValue = null;

    // One of ProductSnippetBuilder::CONDITIONS, or null when the shop does not state it - null publishes nothing rather than claiming the item is new
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $itemCondition = null;

    // What the item weighs once packed, in grams, whole - as prices are held in cents, a tenth of a gram changing no tariff. Null on anything not weighed, which the basket adds up as nothing rather than as zero: half a catalogue weighed would price a parcel as if the rest of it were feathers. Read by PaymentBundle through WeighableBasketItemProviderInterface, the tariff grid and the zones being its business, not this catalogue's
    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\OneToOne(inversedBy: 'productItem', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductItemFile $file = null;

    #[ORM\OneToOne(inversedBy: 'productItem', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductItemMedia $media = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        // Keeps null, as the listener uses it to place the item at the end of the list
        $this->position = $position;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getFile(): ?ProductItemFile
    {
        return $this->file;
    }

    public function setFile(?ProductItemFile $file): static
    {
        $this->file = $file;

        // Keeps the owning side in sync, as getVichMediaPath() walks back to the product through it
        if (null !== $file) {
            $file->setProductItem($this);
        }

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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPriceBefore(): ?int
    {
        return $this->priceBefore;
    }

    public function setPriceBefore(?int $priceBefore): static
    {
        $this->priceBefore = $priceBefore;

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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): static
    {
        $this->gtin = $gtin;

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

    public function getLimitedQuantity(): ?int
    {
        return $this->limitedQuantity;
    }

    public function setLimitedQuantity(?int $limitedQuantity): static
    {
        $this->limitedQuantity = $limitedQuantity;

        return $this;
    }

    public function getOrderedQuantity(): ?int
    {
        return $this->orderedQuantity;
    }

    public function setOrderedQuantity(?int $orderedQuantity): static
    {
        $this->orderedQuantity = $orderedQuantity;

        return $this;
    }

    public function isService(): ?bool
    {
        return $this->service;
    }

    public function setService(?bool $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getItemCondition(): ?string
    {
        return $this->itemCondition;
    }

    public function setItemCondition(?string $itemCondition): static
    {
        $this->itemCondition = $itemCondition;

        return $this;
    }

    public function getMedia(): ?ProductItemMedia
    {
        return $this->media;
    }

    public function setMedia(?ProductItemMedia $media): static
    {
        $this->media = $media;

        // Keeps the owning side in sync, as getVichMediaPath() walks back to the product through it
        if (null !== $media) {
            $media->setProductItem($this);
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGiftCardValue(): ?int
    {
        return $this->giftCardValue;
    }

    public function setGiftCardValue(?int $giftCardValue): static
    {
        $this->giftCardValue = null === $giftCardValue || $giftCardValue <= 0 ? null : $giftCardValue;

        return $this;
    }

    // What tells the checkout this item is money bought in advance rather than something to deliver (see Basket::CONTENT_FLAG_GIFT_CARD)
    public function isGiftCard(): bool
    {
        return null !== $this->giftCardValue && $this->giftCardValue > 0;
    }
}
