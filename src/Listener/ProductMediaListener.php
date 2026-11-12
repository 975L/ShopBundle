<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ShopBundle\Entity\ProductMedia;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Places and signs a product's picture on a write or a real edit only, never on a flush that leaves it unchanged
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductMedia::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: ProductMedia::class)]
class ProductMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new picture goes after its product's last one, signed by the signed-in account
    public function prePersist(ProductMedia $entity, PrePersistEventArgs $event): void
    {
        $this->placeAndSign($entity);
    }

    // A picture whose place was cleared goes back after the others
    public function preUpdate(ProductMedia $entity, PreUpdateEventArgs $event): void
    {
        $this->placeAndSign($entity);
    }

    // Position after the product's other pictures, author kept once given
    private function placeAndSign(ProductMedia $entity): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getProduct()?->getMedias() ?? [] as $media) {
                $maxPosition = max($maxPosition, (int) $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }

        $this->stampUser($entity, false);
    }
}
