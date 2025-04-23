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
use c975L\ShopBundle\Repository\CrowdfundingCounterpartRepository;

#[ORM\Entity(repositoryClass: CrowdfundingCounterpartRepository::class)]
#[ORM\Table(name: 'shop_crowdfunding_counterpart')]
class CrowdfundingCounterpart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $limitedQuantity = null;

    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $orderedQuantity = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $expectedDelivery = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(type: 'boolean')]
    private bool $requiresShipping = false;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $lotteryTickets = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $modification = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'counterparts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    #[ORM\OneToMany(targetEntity: CrowdfundingContributorCounterpart::class, mappedBy: 'counterpart')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $contributorCounterparts;

    #[ORM\OneToOne(inversedBy: 'crowdfundingCounterpart', cascade: ['persist', 'remove'])]
    private ?CrowdfundingCounterpartMedia $media = null;

    #[ORM\ManyToOne(inversedBy: 'crowdfundingCounterparts')]
    private ?User $user = null;

    public function __construct()
    {
        $this->contributors = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title;
    }

    public function toArray()
    {
        return get_object_vars($this);
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getExpectedDelivery(): ?string
    {
        return $this->expectedDelivery;
    }

    public function setExpectedDelivery(?string $expectedDelivery): static
    {
        $this->expectedDelivery = $expectedDelivery;

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

    public function getRequiresShipping(): bool
    {
        return $this->requiresShipping;
    }

    public function setRequiresShipping(bool $requiresShipping): self
    {
        $this->requiresShipping = $requiresShipping;

        return $this;
    }

    public function getLotteryTickets(): int
    {
        return $this->lotteryTickets;
    }

    public function setLotteryTickets(int $lotteryTickets): self
    {
        $this->lotteryTickets = max(0, min(10, $lotteryTickets));

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

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getContributors(): Collection
    {
        $contributors = new ArrayCollection();

        foreach ($this->contributorCounterparts as $relation) {
            $contributors->add($relation->getContributor());
        }

        return $contributors;
    }

    public function addContributor(CrowdfundingContributor $contributor): static
    {
        if (!$this->contributors->contains($contributor)) {
            $this->contributors->add($contributor);
            $contributor->setCrowdfundingCounterpart($this);
        }

        return $this;
    }

    public function removeContributor(CrowdfundingContributor $contributor): static
    {
        if ($this->contributors->removeElement($contributor)) {
            // set the owning side to null (unless already changed)
            if ($contributor->getCrowdfundingCounterpart() === $this) {
                $contributor->setCrowdfundingCounterpart(null);
            }
        }

        return $this;
    }

    public function getMedia(): ?CrowdfundingCounterpartMedia
    {
        return $this->media;
    }

    public function setMedia(?CrowdfundingCounterpartMedia $media): static
    {
        $this->media = $media;

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
