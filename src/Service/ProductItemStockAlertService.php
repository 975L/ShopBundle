<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Email\ShopEmailTemplateProvider;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemStockAlert;
use c975L\ShopBundle\Repository\ProductItemStockAlertRepository;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The whole life of a "tell me when it is back" subscription: taken on the item's page, told by the nightly command, dropped by the link its own e-mail carried
class ProductItemStockAlertService implements ProductItemStockAlertServiceInterface
{
    public function __construct(
        private readonly ProductItemStockAlertRepository $stockAlertRepository,
        private readonly ProductStateServiceInterface $productStateService,
        private readonly EntityManagerInterface $em,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly EmailService $emailService,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function subscribe(ProductItem $productItem, string $email, string $locale): bool
    {
        // Nothing to wait for on an item still buyable, and nothing promised on one withdrawn from sale: both would take an address for an e-mail that is never sent
        if (!$this->productStateService->isItemSoldOut($productItem)) {
            return false;
        }

        $stockAlert = $this->stockAlertRepository->findOneByItemAndEmail($productItem, $email);

        if (null === $stockAlert) {
            $stockAlert = new ProductItemStockAlert()
                ->setProductItem($productItem)
                ->setEmail($email)
                ->setLocale($locale)
            ;
            $this->em->persist($stockAlert);
        } else {
            // Somebody who was told once and is waiting again: the unique constraint leaves no second row to create, so the one they have goes back on the list
            $stockAlert->renew($locale);
        }

        $this->em->flush();

        return true;
    }

    public function unsubscribe(ProductItemStockAlert $stockAlert): void
    {
        $this->em->remove($stockAlert);
        $this->em->flush();
    }

    public function notifyPending(int $limit): int
    {
        $sent = 0;

        foreach ($this->stockAlertRepository->findPending($limit) as $stockAlert) {
            if (!$this->isBackOnSale($stockAlert->getProductItem())) {
                continue;
            }

            // A send that failed leaves the row waiting rather than marked: the next run tries again, which is the whole point of holding the queue in the database
            if (!$this->send($stockAlert)) {
                continue;
            }

            $stockAlert->setNotifiedAt(new \DateTimeImmutable());
            ++$sent;

            // Written one by one rather than once at the end: a single row throwing halfway through would otherwise lose the marks of everyone already served, and the next run would mail them all a second time
            $this->em->flush();
        }

        return $sent;
    }

    public function countPending(): int
    {
        return $this->stockAlertRepository->countPending();
    }

    // An item is worth writing about only if the visitor who clicks can actually buy it: back in stock, still on sale, and on a product the shop still shows
    private function isBackOnSale(?ProductItem $productItem): bool
    {
        if (null === $productItem || !$productItem->isPublished() || !$this->productStateService->isItemAvailable($productItem)) {
            return false;
        }

        $product = $productItem->getProduct();
        $availableAt = null === $product ? null : $product->getAvailableAt();

        return null !== $product
            && $product->isPublished()
            && !$product->isDeleted()
            && (null === $availableAt || $availableAt <= new \DateTime());
    }

    // Composes the alert from the EmailTemplate of that name, in the language the subscription was taken in - there is no order here to read a locale from, which is why the row carries its own
    private function send(ProductItemStockAlert $stockAlert): bool
    {
        $productItem = $stockAlert->getProductItem();
        $product = null === $productItem ? null : $productItem->getProduct();

        if (null === $productItem || null === $product) {
            return false;
        }

        $html = $this->emailTemplateRenderer->renderNamed(
            ShopEmailTemplateProvider::BACK_IN_STOCK,
            [
                'item_title' => trim($product->getTitle() . ' — ' . $productItem->getTitle()),
                'product_url' => $this->urlGenerator->generate('product_display', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'unsubscribe_url' => $this->urlGenerator->generate('shop_stock_alert_unsubscribe', ['token' => $stockAlert->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            $stockAlert->getLocale(),
        );

        // Declared by this bundle and stored by no site is impossible on an installed ShopBundle: raising says the installation is half-done, where sending a blank body would tell nobody
        if (null === $html) {
            throw new \LogicException(sprintf('No email template named "%s" is declared or stored, so this alert has no body to send.', ShopEmailTemplateProvider::BACK_IN_STOCK));
        }

        return $this->emailService->send(new EmailSendRequest(
            subject: $this->buildSubject($stockAlert),
            context: [],
            html: $html,
            from: $this->config('shop-email-from'),
            fromName: $this->config('shop-email-from-name'),
            to: $stockAlert->getEmail(),
            replyTo: $this->config('shop-email-reply-to'),
            replyToName: $this->config('shop-email-reply-to-name'),
            // renderNamed() has already wrapped the body through EmailLayoutRegistry - wrapping it again would put the site's layout inside itself
            wrapLayout: false,
        ));
    }

    // "<shop name> - <what this e-mail is about>", the shape a basket e-mail has minus the order number this one has no order to name
    private function buildSubject(ProductItemStockAlert $stockAlert): string
    {
        return trim((string) $this->configService->get('shop-name'))
            . ' - ' . $this->translator->trans('label.back_in_stock_subject', [], 'shop', $stockAlert->getLocale());
    }

    // A key left blank comes back as null rather than as an empty string, so UiBundle falls back on the site-wide "email-*" address instead of building a broken one
    private function config(string $key): ?string
    {
        return $this->configService->get($key) ?: null;
    }
}
