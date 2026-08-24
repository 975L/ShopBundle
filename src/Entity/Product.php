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
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_product')]
#[UniqueEntity('slug')]
class Product implements \Stringable, HasBlocksInterface
{
    use HasBlocksTrait;

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

    // The maker of the product, published as the graph's Brand node - Google Merchant Center declines a branded offer that names none. Left empty on anything made in-house, whose brand is the shop itself
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $availableAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // A product is written before it is sold: it stays out of the catalogue, of the sitemap, of the search and of every block until it is published, its sheet answering 404 in the meantime - an editor reads it through the preview action. The column defaults to true so an existing catalogue stays online the day it is created, the property to false so a product written from now on starts as a draft
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = false;

    // The recycle bin: a trashed product keeps everything it holds and answers 410 on its own url, for as long as it can still be restored (see ProductCrudController)
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDeleted = false;

    // The words printed on the recto of the card this product sells, beside its picture and the amount, i.e. what makes it a birthday card rather than a voucher. Held on the product and not on its items: the visual is what a customer picks, the amounts under it being that very card at three prices (see ProductItem::$giftCardValue). Null on everything that is not a gift card
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $giftCardText = null;

    // Whether the code is hidden under a panel to be scratched off, the way a card on a rack is. Not decoration alone: scratched, the code is not written in the page at all and is asked for only once the panel is rubbed off, where a card sold without one prints it as it stands - and a page pasted into a chat is unfurled by a robot that fetches it and runs no script (see PaymentBundle's GiftCardController)
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $giftCardScratch = true;

    #[ORM\OneToMany(targetEntity: ProductMedia::class, mappedBy: 'product', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $medias;

    #[ORM\OneToMany(targetEntity: ProductItem::class, mappedBy: 'product', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    #[ORM\ManyToMany(targetEntity: ProductCategory::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'shop_product_category_link')]
    private Collection $categories;

    // What the product sheet says beyond its items: a banner, arguments, a gallery, a FAQ - composed in the back-office with UiBundle's kinds rather than in a template of its own
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'shop_product_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    // The products an editor picked to go with this one, which the affinity calculation cannot know before anything has been sold. Deliberately one-way: "goes with" is not always mutual, a case going with a phone where the phone leads on its own
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'shop_product_related')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $relatedProducts;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->blocks = new ArrayCollection();
        $this->relatedProducts = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
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

    /**
     * @return Collection<int, ProductMedia>
     */
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

    public function getAvailableAt(): ?\DateTimeInterface
    {
        return $this->availableAt;
    }

    public function setAvailableAt(?\DateTimeInterface $availableAt): static
    {
        $this->availableAt = $availableAt;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        // Keeps null, as the listener uses it to place the product at the end of the list
        $this->position = $position;

        return $this;
    }

    public function getGiftCardText(): ?string
    {
        return $this->giftCardText;
    }

    public function setGiftCardText(?string $giftCardText): static
    {
        $this->giftCardText = null === $giftCardText || '' === trim($giftCardText) ? null : trim($giftCardText);

        return $this;
    }

    public function hasGiftCardScratch(): bool
    {
        return $this->giftCardScratch;
    }

    public function setGiftCardScratch(bool $giftCardScratch): static
    {
        $this->giftCardScratch = $giftCardScratch;

        return $this;
    }

    // Whether this product is a card and not an article: answered off its items rather than stored twice, the amount being what makes one (see ProductItem::isGiftCard()). What the "gift card" fieldset of the back-office and the shop_gift_cards block both read
    public function isGiftCard(): bool
    {
        foreach ($this->items as $item) {
            if ($item->isGiftCard()) {
                return true;
            }
        }

        return false;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    // Trashing a product unpublishes it too, the two never disagreeing: a row of the recycle bin is out of the catalogue whatever its own switch said before
    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        if ($isDeleted) {
            $this->isPublished = false;
        }

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

    /**
     * @return Collection<int, ProductItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * The items the shop is standing behind, which is what the sheet lists and what the price, the badge and the offers of the graph are read from - getItems() above keeps holding them all, being what the back-office edits and what an export carries.
     *
     * @return Collection<int, ProductItem>
     */
    public function getPublishedItems(): Collection
    {
        return $this->items->filter(static fn (ProductItem $item): bool => $item->isPublished());
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

    /**
     * @return Collection<int, ProductCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(ProductCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(ProductCategory $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getRelatedProducts(): Collection
    {
        return $this->relatedProducts;
    }

    public function addRelatedProduct(self $product): static
    {
        // A product going with itself would fill the recommendations of its own sheet with the sheet the visitor is already reading
        if ($product !== $this && !$this->relatedProducts->contains($product)) {
            $this->relatedProducts->add($product);
        }

        return $this;
    }

    public function removeRelatedProduct(self $product): static
    {
        $this->relatedProducts->removeElement($product);

        return $this;
    }
}
