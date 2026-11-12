<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener;

use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Entity\ProductItemMedia;
use c975L\ShopBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Signs an item's picture and its paid file with the account that first gave them a file - on a write or a real edit only, never on a flush that leaves them unchanged
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItemMedia::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: ProductItemMedia::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ProductItemFile::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: ProductItemFile::class)]
class ProductItemMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new row takes the signed-in account as its author
    public function prePersist(ProductItemMedia | ProductItemFile $entity, PrePersistEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }

    // A placeholder written by no account (see ProductItemListener::prePersist) takes the one that later fills it
    public function preUpdate(ProductItemMedia | ProductItemFile $entity, PreUpdateEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }
}
