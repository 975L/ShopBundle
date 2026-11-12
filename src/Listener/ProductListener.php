<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use c975L\ShopBundle\Repository\ProductRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Dates and signs a product when it is written and when it is really edited - never on a flush that leaves it unchanged, a visitor's basket included (see UserTrait)
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Product::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Product::class)]
class ProductListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly ProductRepository $productRepository,
    ) {
    }

    // A new product is placed after the others, dated and signed - a date it already carries kept, so a row built with its own (a frozen demo dataset, an imported catalog) keeps it
    public function prePersist(Product $entity, PrePersistEventArgs $event): void
    {
        $this->placeLast($entity);

        if (null === $entity->getCreation()) {
            $entity->setCreation(new \DateTime());
        }
        if (null === $entity->getModification()) {
            $entity->setModification(new \DateTime());
        }

        $this->stampUser($entity, false);
    }

    // Doctrine only calls it on a real change set, and recomputes it afterwards, so the date and the author written here are saved with it
    public function preUpdate(Product $entity, PreUpdateEventArgs $event): void
    {
        $this->placeLast($entity);
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }

    // Read as a scalar off the whole table: findAll() only returns what the public may see now that a product can be hidden, and a new one placed after those alone would collide with a hidden one sitting further down
    private function placeLast(Product $entity): void
    {
        if (null === $entity->getPosition()) {
            $entity->setPosition($this->productRepository->findMaxPosition() + 5);
        }
    }
}
