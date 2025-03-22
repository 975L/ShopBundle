<?php

namespace c975L\ShopBundle\Listener\Traits;

// Defines methods related to image
trait UserTrait
{
    // Sets the user
    public function setUser($entity): void
    {
        $entity->setUser($this->security->getUser());
        $this->entityManager->persist($entity);
    }
}
