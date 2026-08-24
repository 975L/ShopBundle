<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Entity;

use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use Doctrine\ORM\Mapping as ORM;

// One visitor waiting on one item coming back. Held against the item rather than the product because that is where the stock is: somebody waiting on the paperback is not told the ebook is back
#[ORM\Entity(repositoryClass: ProductItemStockAlertRepository::class)]
#[ORM\Table(name: 'shop_product_item_stock_alert')]
#[ORM\UniqueConstraint(name: 'uniq_stock_alert_item_email', columns: ['product_item_id', 'email'])]
#[ORM\Index(name: 'idx_stock_alert_pending', columns: ['product_item_id', 'notified_at'])]
class ProductItemStockAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The item deleted takes its waiting list with it: what nobody can buy any more is not worth being told about
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProductItem $productItem = null;

    // The same length PaymentBundle's Basket gives its own email column, the two holding addresses of the same visitors
    #[ORM\Column(length: 100)]
    private string $email;

    // The language the subscription was taken in, there being no order to read it from: the alert is composed in it rather than in whatever the nightly command happens to run under
    #[ORM\Column(length: 5)]
    private string $locale;

    // What the unsubscribe link carries, in place of the address itself - a link naming an email address is a link that leaks one
    #[ORM\Column(length: 16, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // Null for as long as nobody has been told. Kept rather than deleted on sending, so a second stockout does not silently re-mail everyone the first one already served
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(8));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductItem(): ?ProductItem
    {
        return $this->productItem;
    }

    public function setProductItem(?ProductItem $productItem): self
    {
        $this->productItem = $productItem;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): self
    {
        $this->notifiedAt = $notifiedAt;

        return $this;
    }

    // Puts the row back on the waiting list, which is what re-subscribing after a previous alert amounts to: the unique constraint on (item, email) leaves no second row to create
    public function renew(string $locale): self
    {
        $this->locale = $locale;
        $this->notifiedAt = null;
        $this->createdAt = new \DateTimeImmutable();

        return $this;
    }
}
