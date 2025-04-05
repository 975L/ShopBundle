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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Repository\PaymentRepository;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'shop_stripe_payment')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isFinished = false;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $stripeToken = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $stripeMethod = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\OneToOne(mappedBy: 'payment', cascade: ['persist', 'remove'])]
    private ?Basket $basket = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    private ?User $user = null;

    public function __toString()
    {
        return $this->id;
    }

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

    public function setAmount(?int $amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
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

    public function setStripeToken(?string $stripeToken)
    {
        $this->stripeToken = $stripeToken;

        return $this;
    }

    public function getStripeToken(): ?string
    {
        return $this->stripeToken;
    }

    public function setStripeMethod(?string $stripeMethod)
    {
        $this->stripeMethod = $stripeMethod;

        return $this;
    }

    public function getStripeMethod(): ?string
    {
        return $this->stripeMethod;
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
