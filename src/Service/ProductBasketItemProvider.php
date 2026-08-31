<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Contract\GiftCardDesign;
use c975L\PaymentBundle\Contract\WeighableBasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Service\GiftCardService;
use c975L\PaymentBundle\Service\VatCalculator;
use c975L\ShopBundle\Message\ProductItemDownloadMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Plugs product items into PaymentBundle's Basket/checkout engine (see BasketItemProviderInterface)
class ProductBasketItemProvider implements BasketItemProviderInterface, WeighableBasketItemProviderInterface
{
    public function __construct(
        private readonly ProductItemServiceInterface $productItemService,
        private readonly MessageBusInterface $messageBus,
        private readonly GiftCardService $giftCardService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'product';
    }

    public function findItem(int | string $id): ?object
    {
        return $this->productItemService->findOneById((int) $id);
    }

    public function validateAddition(object $item, int $quantity): ?string
    {
        // A removal never needs stock, so it must not be blocked by an exhausted item
        if ($quantity <= 0) {
            return null;
        }

        // The sheet of a hidden or of a trashed product answers 404 or 410, but its items keep the ids an old page or a stale basket still carries: nothing is bought of a product the shop is not standing behind
        $product = $item->getProduct();
        if (null === $product || $product->isHidden() || $product->isDeleted()) {
            return $this->translator->trans('label.unavailable', [], 'shop');
        }

        // Same reasoning one level down: an item set aside has left the sheet, but an open page or a basket filled before it did still carries its id
        if ($item->isHidden()) {
            return $this->translator->trans('label.unavailable', [], 'shop');
        }

        if (0 === $item->getLimitedQuantity()) {
            return $this->translator->trans('label.unavailable', [], 'shop');
        }

        if ($item->getLimitedQuantity() > 0) {
            $alreadyOrdered = $item->getOrderedQuantity() ?? 0;
            $canAdd = $item->getLimitedQuantity() - $alreadyOrdered;
            if ($canAdd <= 0) {
                return $this->translator->trans('label.no_more_items_available', [], 'shop');
            }
        }

        return null;
    }

    // The only check standing between filling a basket and paying for it. validateAddition() above is asked one click at a time and knows nothing of what the basket already holds, so five clicks on an item with one left pass it five times; here the whole quantity is compared to what is actually left, and a product taken offline or trashed while the basket sat there is caught too
    public function validateCheckout(Basket $basket, array $itemsOfThisKind): ?string
    {
        foreach ($itemsOfThisKind as $id => $itemContent) {
            $item = $this->productItemService->findOneById((int) $id);

            // Deleted outright while the basket held it: there is nothing left to sell and nothing left to name
            if (null === $item) {
                return $this->translator->trans('label.unavailable', [], 'shop');
            }

            $error = $this->validateAddition($item, (int) $itemContent['quantity']);
            if (null !== $error) {
                return $error;
            }

            // What validateAddition() cannot ask, having only ever seen one click at a time
            if ($item->getLimitedQuantity() > 0 && (int) $itemContent['quantity'] > $item->getLimitedQuantity() - ($item->getOrderedQuantity() ?? 0)) {
                return $this->translator->trans('label.no_more_items_available', [], 'shop');
            }
        }

        return null;
    }

    public function toBasketData(object $item, int $quantity): array
    {
        $itemData = $item->toArray();
        unset($itemData['product'], $itemData['creation'], $itemData['position'], $itemData['modification'], $itemData['user']);
        $itemData['media'] = $item->getMedia() ? $item->getMedia()->getName() : null;
        $itemData['file'] = $item->getFile() ? $item->getFile()->getName() : null;
        $itemData['size'] = $item->getFile() ? $item->getFile()->getSize() : null;

        $product = $item->getProduct();

        return [
            'item' => $itemData,
            'parent' => [
                'title' => $product->getTitle(),
                'slug' => $product->getSlug(),
                'image' => $product->getMedias()->isEmpty() ? null : $product->getMedias()[0]->getName(),
                // What the card sold here is printed with, carried across the checkout with everything else the basket copies: the card is minted from the payment provider's own request, which knows nothing of this catalogue
                'giftCardText' => $product->getGiftCardText(),
                'giftCardScratch' => $product->hasGiftCardScratch(),
            ],
            'type' => 'product',
            'quantity' => $quantity,
            // Taken out of what the line is sold for, prices being held VAT included (see PaymentBundle's VatCalculator)
            'totalVat' => VatCalculator::included($quantity * $item->getPrice(), (float) $item->getVat()),
            'total' => $quantity * $item->getPrice(),
        ];
    }

    public function getContentFlags(array $itemData): int
    {
        // Added on top of what the item is delivered as rather than instead of it: a card sent by e-mail is a service, one printed and posted is a physical item, and both are money bought in advance - which is the whole of what the checkout reads this flag for (see Basket::CONTENT_FLAG_GIFT_CARD)
        $giftCard = empty($itemData['item']['giftCardValue']) ? 0 : Basket::CONTENT_FLAG_GIFT_CARD;

        // Read with defaults, not as a shape that is guaranteed: an order's items are a snapshot frozen the day it was placed, and one taken before this bundle knew about services or files carries neither key - a years-old order still has to be displayed, e-mailed and reprinted
        if (null !== ($itemData['item']['file'] ?? null)) {
            return Basket::CONTENT_FLAG_DIGITAL | $giftCard;
        }
        if (true === ($itemData['item']['service'] ?? false)) {
            return Basket::CONTENT_FLAG_SERVICE | $giftCard;
        }

        return Basket::CONTENT_FLAG_PHYSICAL | $giftCard;
    }

    // What the line weighs, the article's own weight taken as many times as it was ordered - see WeighableBasketItemProviderInterface
    public function getWeight(array $itemData): ?int
    {
        // Only what is posted weighs anything: a download and a service leave the parcel alone, and so does a card sent by e-mail, which getContentFlags() already reads as a service
        if (0 === (Basket::CONTENT_FLAG_PHYSICAL & $this->getContentFlags($itemData))) {
            return null;
        }

        // Read with defaults, like the flags above: a line snapshotted before this bundle weighed anything carries no such key, and an unweighed article is counted as nothing rather than as zero
        $weight = $itemData['item']['weight'] ?? null;

        return null === $weight ? null : (int) $weight * max(1, (int) ($itemData['quantity'] ?? 1));
    }

    public function onBasketValidated(Basket $basket, array $itemsOfThisKind, array $requestData): array
    {
        // A plain product purchase carries nothing across the payment: what is ordered is already on the basket
        return [];
    }

    public function onBasketPaid(Basket $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        $hasDigital = false;

        foreach ($itemsOfThisKind as $id => $itemContent) {
            // Everything owed to the buyer is read off the basket, which froze the order the day it was placed: an item edited or deleted since must not cost them the file they paid for, and validateCheckout() already anticipates that deletion
            if (!empty($itemContent['item']['file'])) {
                $hasDigital = true;
            }

            $this->issueGiftCards($basket, $itemContent);
            $this->countOrdered($id, $itemContent['quantity']);
        }

        if ($hasDigital) {
            $this->messageBus->dispatch(new ProductItemDownloadMessage($basket->getId()));
        }
    }

    // A card is born here and nowhere else: the order is settled, so the money behind it exists. One card per unit bought, each with its own code - two cards of 20 € are not one of 40
    private function issueGiftCards(Basket $basket, array $itemContent): void
    {
        $giftCardValue = (int) ($itemContent['item']['giftCardValue'] ?? 0);
        if ($giftCardValue <= 0) {
            return;
        }

        // Read off the basket and not off the product: the visual it was bought with is what the card keeps, whatever the catalogue has been changed to since
        $design = new GiftCardDesign(
            $itemContent['parent']['image'] ?? null,
            $itemContent['parent']['giftCardText'] ?? null,
            $itemContent['parent']['giftCardScratch'] ?? true,
        );

        // The amount too: a card is worth what was paid for it, and a value re-priced between the checkout and the payment notice must not mint a card of another amount
        for ($i = 0; $i < $itemContent['quantity']; ++$i) {
            $this->giftCardService->issue($giftCardValue, (string) ($itemContent['item']['currency'] ?? 'eur'), $basket, null, $design);
        }
    }

    // Last, and the only thing the live catalogue is needed for: an item deleted since is simply no longer counted
    private function countOrdered(int | string $id, int $quantity): void
    {
        $item = $this->productItemService->findOneById($id);
        if (null === $item) {
            return;
        }

        $item->setOrderedQuantity(($item->getOrderedQuantity() ?? 0) + $quantity);
    }
}
