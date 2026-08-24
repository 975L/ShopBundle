<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Listener\Traits;

use c975L\ConfigBundle\Contract\UserInterface;

trait UserTrait
{
    // Sets the user
    public function setUser($entity): void
    {
        // Security only guarantees its own UserInterface, the entities relate to the c975L one the application's User entity implements
        $currentUser = $this->security->getUser();

        if ($currentUser instanceof UserInterface) {
            if (null === $entity->getUser()) {
                // New entity
                $entity->setUser($currentUser);
            } elseif (method_exists($entity, 'getModification') && null !== $entity->getModification() && $entity->getModification() > $entity->getCreation()) {
                // Updated entity
                $entity->setUser($currentUser);
            }

            $this->entityManager->persist($entity);
        }
    }
}
