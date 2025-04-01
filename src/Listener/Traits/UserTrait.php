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
            if ($entity->getId() === null) {
                $entity->setUser($currentUser);
            }
            // Cas 2: Entité existante en cours de modification - mettre à jour l'utilisateur
            else if ($entity->getModification() != null && $entity->getModification() > $entity->getCreation()) {
                $entity->setUser($currentUser);
            }

            $this->entityManager->persist($entity);
        }
    }
}
