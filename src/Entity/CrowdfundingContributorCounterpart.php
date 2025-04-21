<?php

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Repository\ContributorCounterpartRepository;

#[ORM\Entity(repositoryClass: ContributorCounterpartRepository::class)]
#[ORM\Table(name: 'shop_crowdfunding_contributor_counterpart')]
class CrowdfundingContributorCounterpart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CrowdfundingContributor::class, inversedBy: 'contributorCounterparts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CrowdfundingContributor $contributor = null;

    #[ORM\ManyToOne(targetEntity: CrowdfundingCounterpart::class, inversedBy: 'contributorCounterparts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CrowdfundingCounterpart $counterpart = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $quantity = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }
}