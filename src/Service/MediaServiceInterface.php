<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Media;

interface MediaServiceInterface
{
    /**
     * @return Media[]
     */
    public function findAll(): array;

    /**
     * Blanks out the row of a media whose file is gone from disk, so the database stops advertising it.
     *
     * @param string $file the stored file name to look the media up by
     */
    public function updateDatabaseByName(string $file): void;
}
