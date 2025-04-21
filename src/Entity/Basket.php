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
use c975L\ShopBundle\Repository\BasketRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BasketRepository::class)]
#[ORM\Table(name: 'shop_basket')]
#[UniqueEntity('number')]

class Basket
{
    public const CONTENT_FLAG_DIGITAL = 1;
    public const CONTENT_FLAG_PHYSICAL = 2;
    public const CONTENT_FLAG_CF_SHIPPING = 4;
    public const CONTENT_FLAG_CF_DIGITAL = 8;

    // Pre-defined flags
    public const FLAG_PRODUCT_MIXED = self::CONTENT_FLAG_DIGITAL | self::CONTENT_FLAG_PHYSICAL; // 3
    public const FLAG_CF_MIXED = self::CONTENT_FLAG_CF_DIGITAL | self::CONTENT_FLAG_CF_SHIPPING; // 12
    public const FLAG_DIGITAL_ONLY = self::CONTENT_FLAG_DIGITAL | self::CONTENT_FLAG_CF_DIGITAL; // 9
    public const FLAG_NEEDS_SHIPPING = self::CONTENT_FLAG_PHYSICAL | self::CONTENT_FLAG_CF_SHIPPING; // 6
    public const FLAG_MIXED = self::FLAG_DIGITAL_ONLY | self::FLAG_NEEDS_SHIPPING; // 15

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $number = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $securityToken = null;

    #[ORM\Column]
    private array $items = [];

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $status = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $country = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private ?int $total = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private ?int $shipping = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(min:3, max: 5)]
    private ?string $currency = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $contentflags = 0;

    #[ORM\OneToOne(inversedBy: 'basket')]
    private ?Payment $payment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $modification = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $itemsShipped = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $counterpartsShipped = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $downloaded = null;

    #[ORM\ManyToOne(inversedBy: 'baskets')]
    private ?User $user = null;

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function __toString()
    {
        return $this->number ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getSecurityToken(): ?string
    {
        return $this->securityToken;
    }

    public function setSecurityToken(?string $securityToken): self
    {
        $this->securityToken = $securityToken;

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getShipping(): ?int
    {
        return $this->shipping;
    }

    public function setShipping(int $shipping): static
    {
        $this->shipping = $shipping;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getContentFlags(): int
    {
        return $this->contentflags;
    }

    public function setContentFlags(int $contentflags): static
    {
        $this->contentflags = $contentflags;

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

    public function getItemsShipped(): ?DateTimeInterface
    {
        return $this->itemsShipped;
    }

    public function setItemsShipped(?DateTimeInterface $itemsShipped): static
    {
        $this->itemsShipped = $itemsShipped;

        return $this;
    }

    public function getCounterpartsShipped(): ?DateTimeInterface
    {
        return $this->counterpartsShipped;
    }

    public function setCounterpartsShipped(?DateTimeInterface $counterpartsShipped): static
    {
        $this->counterpartsShipped = $counterpartsShipped;

        return $this;
    }

    public function getDownloaded(): ?DateTimeInterface
    {
        return $this->downloaded;
    }

    public function setDownloaded(?DateTimeInterface $downloaded): static
    {
        $this->downloaded = $downloaded;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;

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
