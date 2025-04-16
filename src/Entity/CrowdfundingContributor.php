<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Entity\CrowdfundingCounterpart;
use c975L\ShopBundle\Repository\CrowdfundingContributorRepository;

#[ORM\Entity(repositoryClass: CrowdfundingContributorRepository::class)]
#[ORM\Table(name: 'shop_crowdfunding_contributor')]
class CrowdfundingContributor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 100)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'contributors')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    #[ORM\ManyToOne(targetEntity: CrowdfundingCounterpart::class, inversedBy: 'contributors')]
    #[ORM\JoinColumn(nullable: true)]
    private ?CrowdfundingCounterpart $crowdfundingCounterpart = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getCrowdfundingCounterpart(): ?CrowdfundingCounterpart
    {
        return $this->crowdfundingCounterpart;
    }

    public function setCrowdfundingCounterpart(?CrowdfundingCounterpart $crowdfundingCounterpart): static
    {
        $this->crowdfundingCounterpart = $crowdfundingCounterpart;

        return $this;
    }
}
