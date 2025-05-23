<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use Symfony\Component\Form\Form;
use c975L\ShopBundle\Entity\Crowdfunding;

interface CrowdfundingServiceInterface
{
    public function createForm(string $name, $object): Form;

    public function findAll();

    public function findAllSorted();

    public function findOneById(int $id): ?Crowdfunding;
}
