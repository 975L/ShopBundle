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
use c975L\ShopBundle\Entity\CrowdfundingVideo;
use Doctrine\Common\Collections\ArrayCollection;
use c975L\ShopBundle\Repository\CrowdfundingRepository;

#[ORM\Entity(repositoryClass: CrowdfundingRepository::class)]
#[ORM\Table(name: 'shop_crowdfunding')]
class Crowdfunding
{
    private string $type = 'crowdfunding';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(length: 50)]
    private ?string $authorName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $authorPresentation = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorWebsite = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $amountGoal = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountAchieved = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $useFor = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $beginDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\OneToMany(targetEntity: CrowdfundingMedia::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $medias;

    #[ORM\OneToMany(targetEntity: CrowdfundingContributor::class, mappedBy: 'crowdfunding')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $contributors;

    #[ORM\OneToMany(targetEntity: CrowdfundingNews::class, mappedBy: 'crowdfunding', cascade: ['remove'])]
    #[ORM\OrderBy(['publishedDate' => 'DESC'])]
    private Collection $news;

    #[ORM\OneToMany(targetEntity: CrowdfundingCounterpart::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['price' => 'ASC'])]
    private Collection $counterparts;

    #[ORM\OneToMany(targetEntity: CrowdfundingVideo::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'])]
    private Collection $videos;

    #[ORM\OneToMany(targetEntity: Lottery::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'])]
    private Collection $lotteries;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $modification = null;

    #[ORM\ManyToOne(inversedBy: 'crowdfundings')]
    private ?User $user = null;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->contributors = new ArrayCollection();
        $this->news = new ArrayCollection();
        $this->counterparts = new ArrayCollection();
        $this->lotteries = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title;
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

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorPresentation(): ?string
    {
        return $this->authorPresentation;
    }

    public function setAuthorPresentation(string $authorPresentation): static
    {
        $this->authorPresentation = $authorPresentation;

        return $this;
    }

    public function getAuthorWebsite(): ?string
    {
        return $this->authorWebsite;
    }

    public function setAuthorWebsite(?string $authorWebsite): static
    {
        $this->authorWebsite = $authorWebsite;

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

    public function getAmountGoal(): ?int
    {
        return $this->amountGoal;
    }

    public function setAmountGoal(int $amountGoal): static
    {
        $this->amountGoal = $amountGoal;

        return $this;
    }

    public function getAmountAchieved(): ?int
    {
        return $this->amountAchieved;
    }

    public function setAmountAchieved(?int $amountAchieved): static
    {
        $this->amountAchieved = $amountAchieved;

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

    public function getUseFor(): ?string
    {
        return $this->useFor;
    }

    public function setUseFor(string $useFor): static
    {
        $this->useFor = $useFor;

        return $this;
    }

    public function getBeginDate(): ?DateTimeInterface
    {
        return $this->beginDate;
    }

    public function setBeginDate(?DateTimeInterface $beginDate): static
    {
        $this->beginDate = $beginDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(CrowdfundingMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeMedia(CrowdfundingMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            // set the owning side to null (unless already changed)
            if ($media->getCrowdfunding() === $this) {
                $media->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getContributors(): Collection
    {
        return $this->contributors;
    }

    public function addContributor(CrowdfundingContributor $contributor): static
    {
        if (!$this->contributors->contains($contributor)) {
            $this->contributors->add($contributor);
            $contributor->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeContributor(CrowdfundingContributor $contributor): static
    {
        if ($this->contributors->removeElement($contributor)) {
            // set the owning side to null (unless already changed)
            if ($contributor->getCrowdfunding() === $this) {
                $contributor->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(CrowdfundingNews $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeNews(CrowdfundingNews $news): static
    {
        if ($this->news->removeElement($news)) {
            // set the owning side to null (unless already changed)
            if ($news->getCrowdfunding() === $this) {
                $news->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getCounterparts(): Collection
    {
        return $this->counterparts;
    }

    public function addCounterpart(CrowdfundingCounterpart $counterpart): static
    {
        if (!$this->counterparts->contains($counterpart)) {
            $this->counterparts->add($counterpart);
            $counterpart->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeCounterpart(CrowdfundingCounterpart $counterpart): static
    {
        if ($this->counterparts->removeElement($counterpart)) {
            // set the owning side to null (unless already changed)
            if ($counterpart->getCrowdfunding() === $this) {
                $counterpart->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(CrowdfundingVideo $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeVideo(CrowdfundingVideo $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getCrowdfunding() === $this) {
                $video->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getLotteries(): Collection
    {
        return $this->lotteries;
    }

    public function addLottery(Lottery $lottery): static
    {
        if (!$this->lotteries->contains($lottery)) {
            $this->lotteries->add($lottery);
            $lottery->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeLottery(Lottery $lottery): static
    {
        if ($this->lotteries->removeElement($lottery)) {
            // set the owning side to null (unless already changed)
            if ($lottery->getCrowdfunding() === $this) {
                $lottery->setCrowdfunding(null);
            }
        }

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
