<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use c975L\ShopBundle\Repository\ProductAffinityRepository;

#[ORM\Entity(repositoryClass: ProductAffinityRepository::class)]
#[ORM\Table(name: 'shop_product_affinity')]
#[ORM\Index(columns: ['product_id_1'], name: 'idx_product_1')]
#[ORM\Index(columns: ['product_id_2'], name: 'idx_product_2')]
#[ORM\Index(columns: ['affinity_score'], name: 'idx_affinity_score')]
class ProductAffinity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id_1', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product1 = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id_2', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product2 = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $coPurchaseCount = 0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => 0])]
    private float $affinityScore = 0.0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastCalculated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct1(): ?Product
    {
        return $this->product1;
    }

    public function setProduct1(?Product $product1): self
    {
        $this->product1 = $product1;

        return $this;
    }

    public function getProduct2(): ?Product
    {
        return $this->product2;
    }

    public function setProduct2(?Product $product2): self
    {
        $this->product2 = $product2;

        return $this;
    }

    public function getCoPurchaseCount(): int
    {
        return $this->coPurchaseCount;
    }

    public function setCoPurchaseCount(int $coPurchaseCount): self
    {
        $this->coPurchaseCount = $coPurchaseCount;

        return $this;
    }

    public function incrementCoPurchaseCount(): self
    {
        $this->coPurchaseCount++;

        return $this;
    }

    public function getAffinityScore(): float
    {
        return $this->affinityScore;
    }

    public function setAffinityScore(float $affinityScore): self
    {
        $this->affinityScore = $affinityScore;

        return $this;
    }

    public function getLastCalculated(): ?\DateTimeInterface
    {
        return $this->lastCalculated;
    }

    public function setLastCalculated(\DateTimeInterface $lastCalculated): self
    {
        $this->lastCalculated = $lastCalculated;

        return $this;
    }
}
