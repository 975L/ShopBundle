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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Product::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Product::class)]
class ProductListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function preFlush(Product $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            // Read as a scalar off the whole table: findAll() only returns what the public may see now that a product can be a draft, and a new one placed after those alone would collide with a draft sitting further down
            $entity->setPosition($this->productRepository->findMaxPosition() + 5);
        }
        $entity->setModification(new \DateTime());
        $this->setUser($entity);
    }

    public function prePersist(Product $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new \DateTime());
    }
}
