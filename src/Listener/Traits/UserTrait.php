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
use Doctrine\ORM\Event\PreUpdateEventArgs;

// Who wrote a row and whether a flush really edited it, shared by the catalog's listeners - read on prePersist and preUpdate only, so a visitor's flush never touches a row it did not change
trait UserTrait
{
    // Names the signed-in account as the entity's author - $overwrite for an edit, otherwise only an entity holding none - a row written by no account (a command, a webhook) keeping the one it has
    private function stampUser(object $entity, bool $overwrite): void
    {
        // Security only guarantees its own UserInterface, the entities relate to the c975L one the application's User entity implements
        $currentUser = $this->security->getUser();

        if ($currentUser instanceof UserInterface && ($overwrite || null === $entity->getUser())) {
            $entity->setUser($currentUser);
        }
    }

    // Whether the change set holds anything beyond $counters, the fields a sale or a download writes on its own
    /** @param list<string> $counters */
    private function isEdited(PreUpdateEventArgs $args, array $counters = []): bool
    {
        return [] !== array_diff(array_keys($args->getEntityChangeSet()), $counters);
    }
}
