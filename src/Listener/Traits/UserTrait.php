<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Traits;

trait UserTrait
{
    // Sets the user
    public function setUser($entity): void
    {
        $currentUser = $this->security->getUser();

        if ($currentUser !== null) {
            // New entity
            if ($entity->getUser() === null) {
                $entity->setUser($currentUser);
            }
            // Updated entity
            elseif (method_exists($entity, 'getModification') && $entity->getModification() !== null && $entity->getModification() > $entity->getCreation()) {
                $entity->setUser($currentUser);
            }

            $this->entityManager->persist($entity);
        }
    }
}
