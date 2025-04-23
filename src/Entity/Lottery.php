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
use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Entity\LotteryPrize;
use c975L\ShopBundle\Entity\LotteryTicket;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use c975L\ShopBundle\Repository\LotteryRepository;

#[ORM\Entity(repositoryClass: LotteryRepository::class)]
#[ORM\Table(name: 'shop_lottery')]
class Lottery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'lotteries', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    #[ORM\Column(length: 13, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $drawDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\OneToMany(targetEntity: LotteryPrize::class, mappedBy: 'lottery', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['rank' => 'ASC'])]
    private Collection $prizes;

    #[ORM\OneToMany(targetEntity: LotteryTicket::class, mappedBy: 'lottery', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $tickets;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $modification = null;

    #[ORM\ManyToOne(inversedBy: 'lotteries')]
    private ?User $user = null;

    public function __construct()
    {
        $this->prizes = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getDrawDate(): ?DateTimeInterface
    {
        return $this->drawDate;
    }

    public function setDrawDate(?DateTimeInterface $drawDate): self
    {
        $this->drawDate = $drawDate;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getPrizes(): Collection
    {
        return $this->prizes;
    }

    public function addPrize(LotteryPrize $prize): self
    {
        if (!$this->prizes->contains($prize)) {
            $this->prizes->add($prize);
            $prize->setLottery($this);
        }

        return $this;
    }

    public function removePrize(LotteryPrize $prize): self
    {
        if ($this->prizes->removeElement($prize)) {
            if ($prize->getLottery() === $this) {
                $prize->setLottery(null);
            }
        }

        return $this;
    }

    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(LotteryTicket $ticket): self
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setLottery($this);
        }
        return $this;
    }

    public function removeTicket(LotteryTicket $ticket): self
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getLottery() === $this) {
                $ticket->setLottery(null);
            }
        }
        return $this;
    }

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(?DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(?DateTimeInterface $modification): self
    {
        $this->modification = $modification;

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