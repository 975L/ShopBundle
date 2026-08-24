<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;

// The directories the back-office uploads land in, the only contents a git deployment does not replay. The private one is the least negotiable of the two: ProductItemFile holds the files customers have paid for, which no re-upload brings back
class ShopBackupPathProvider implements BackupPathProviderInterface
{
    public function getBackupPaths(): array
    {
        return [
            // One path per root: "products" and "items" sit under it, and the collector drops any path nested in another already declared
            new BackupPath('public/medias/shop', BackupPath::MODE_MIRROR),
            new BackupPath('private/medias/shop', BackupPath::MODE_MIRROR),
        ];
    }
}
