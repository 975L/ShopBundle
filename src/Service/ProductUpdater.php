<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ShopBundle\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

// What saving an edited product takes besides its own columns, whoever edits it (the back office, an agent): the edit dated, and its old url sent to the new one when its slug changed
class ProductUpdater
{
    // Where a product's public sheet is served, the prefix a redirect and a "gone" row are written against
    public const string PRODUCT_PATH = '/shop/products/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RedirectRepository $redirectRepository,
    ) {
    }

    // Dates the edit, the items and pictures being rows of their own whose changes leave the product's change set empty (what the sitemap's lastmod reads), and redirects a changed slug, before the flush that saves it
    public function update(Product $product): void
    {
        $product->setModification(new \DateTimeImmutable());

        $originalSlug = $this->entityManager->getUnitOfWork()->getOriginalEntityData($product)['slug'] ?? null;
        if (null !== $originalSlug && $originalSlug !== $product->getSlug()) {
            $this->redirectSlugChange((string) $originalSlug, (string) $product->getSlug());
        }
    }

    // A renamed product leaves its old url behind, which visitors have bookmarked and search engines have indexed: a 301 sends both to the new one rather than to a 404, reusing the row the old slug already had
    private function redirectSlugChange(string $oldSlug, string $newSlug): void
    {
        $fromPath = self::PRODUCT_PATH . $oldSlug;
        $toUrl = self::PRODUCT_PATH . $newSlug;

        // Removes any redirect starting from the new slug, otherwise renaming a product back to a name it already had builds a loop
        $reverseRedirect = $this->redirectRepository->findOneByFromPath($toUrl);
        if (null !== $reverseRedirect) {
            $this->entityManager->remove($reverseRedirect);
        }

        $redirect = $this->redirectRepository->findOneByFromPath($fromPath)
            ?? new Redirect()->setFromPath($fromPath);

        $this->entityManager->persist($redirect->setToUrl($toUrl)->setPermanent(true));
    }
}
