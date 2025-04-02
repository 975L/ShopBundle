<?php

namespace c975L\ShopBundle\Listener\Traits;

// Defines methods related to image
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
