<?php

namespace c975L\ShopBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_product_item_download')]
class ProductItemDownload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $basketId;

    #[ORM\Column(length: 16)]
    private string $token;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column]
    private ?DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private bool $downloaded = false;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $downloadedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBasketId(): int
    {
        return $this->basketId;
    }

    public function setBasketId(int $basketId): self
    {
        $this->basketId = $basketId;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isDownloaded(): bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): self
    {
        $this->downloaded = $downloaded;
        return $this;
    }

    public function getDownloadedAt(): ?\DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function setDownloadedAt(?\DateTimeImmutable $downloadedAt): self
    {
        $this->downloadedAt = $downloadedAt;
        return $this;
    }
}