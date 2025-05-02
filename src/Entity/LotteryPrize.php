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
use c975L\ShopBundle\Repository\LotteryPrizeRepository;

#[ORM\Entity(repositoryClass: LotteryPrizeRepository::class)]
#[ORM\Table(name: 'shop_lottery_prize')]
class LotteryPrize
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: 'smallint')]
    private int $rank;

    #[ORM\ManyToOne(targetEntity: Lottery::class, inversedBy: 'prizes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lottery $lottery = null;

    #[ORM\OneToOne(targetEntity: LotteryTicket::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LotteryTicket $winningTicket = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $drawDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $modification = null;

    #[ORM\ManyToOne(inversedBy: 'lotteryPrizes')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): self
    {
        // Ensure rank is between 1-5
        $this->rank = max(1, min(5, $rank));

        return $this;
    }

    public function getLottery(): ?Lottery
    {
        return $this->lottery;
    }

    public function setLottery(?Lottery $lottery): self
    {
        $this->lottery = $lottery;

        return $this;
    }

    public function getWinningTicket(): ?LotteryTicket
    {
        return $this->winningTicket;
    }

    public function setWinningTicket(?LotteryTicket $ticket): self
    {
        $this->winningTicket = $ticket;

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