<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Repository\LotteryTicketRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: LotteryTicketRepository::class)]
#[ORM\Table(name: 'shop_lottery_ticket')]
#[UniqueEntity('number')]
class LotteryTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Lottery::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lottery $lottery = null;

    #[ORM\ManyToOne(targetEntity: CrowdfundingContributor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CrowdfundingContributor $contributor = null;

    #[ORM\ManyToOne(targetEntity: CrowdfundingCounterpart::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CrowdfundingCounterpart $counterpart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $creation = null;

    public function __toString()
    {
        return $this->getNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

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

    public function getContributor(): ?CrowdfundingContributor
    {
        return $this->contributor;
    }

    public function setContributor(?CrowdfundingContributor $contributor): self
    {
        $this->contributor = $contributor;

        return $this;
    }

    public function getCounterpart(): ?CrowdfundingCounterpart
    {
        return $this->counterpart;
    }

    public function setCounterpart(?CrowdfundingCounterpart $counterpart): self
    {
        $this->counterpart = $counterpart;

        return $this;
    }

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }
}