<?php
/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\PaymentBundle\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'stripe_payment')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isFinished = false;

    #[ORM\Column(length: 48)]
    private ?string $orderId = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 512)]
    private ?string $description = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?int $stripeFee = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $stripeToken = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $stripeTokenType = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $stripeEmail = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\OneToOne(mappedBy: 'payment', cascade: ['persist', 'remove'])]
    private ?Basket $basket = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function isFinished(): ?bool
    {
        return $this->isFinished;
    }

    public function setFinished(bool $isFinished): static
    {
        $this->isFinished = $isFinished;

        return $this;
    }

    public function setOrderId(?string $orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setAmount(?int $amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setDescription(?string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setCurrency(?string $currency)
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function getCurrency(): ?string
    {
        return strtoupper($this->currency);
    }

    public function setStripeFee(?int $stripeFee)
    {
        $this->stripeFee = $stripeFee;

        return $this;
    }

    public function getStripeFee(): int
    {
        return $this->stripeFee;
    }

    public function setStripeToken(?string $stripeToken)
    {
        $this->stripeToken = $stripeToken;

        return $this;
    }

    public function getStripeToken(): ?string
    {
        return $this->stripeToken;
    }

    public function setStripeTokenType(?string $stripeTokenType)
    {
        $this->stripeTokenType = $stripeTokenType;

        return $this;
    }

    public function getStripeTokenType(): ?string
    {
        return $this->stripeTokenType;
    }

    public function setStripeEmail(?string $stripeEmail)
    {
        $this->stripeEmail = $stripeEmail;

        return $this;
    }

    public function getStripeEmail(): ?string
    {
        return $this->stripeEmail;
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

    public function getBasket(): ?Basket
    {
        return $this->basket;
    }

    public function setBasket(?Basket $basket): static
    {
        // unset the owning side of the relation if necessary
        if ($basket === null && $this->basket !== null) {
            $this->basket->setPayment(null);
        }

        // set the owning side of the relation if necessary
        if ($basket !== null && $basket->getPayment() !== $this) {
            $basket->setPayment($this);
        }

        $this->basket = $basket;

        return $this;
    }
}
