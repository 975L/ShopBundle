<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Repository\Traits;

use c975L\UiBundle\Entity\Block;

// The owners of the given blocks, for the repositories of this bundle's block owners (a product, a category, the shop's settings) - what ShopBlockEditUrlProvider points a block back at the screen it was composed on from
trait BlockOwnerRepositoryTrait
{
    // One query, filtered through EXISTS and each owner's blocks fetched whole on an alias of their own: a fetch-join on the filtered alias would load each collection with the matching blocks only, the others then missing for the rest of the request
    /**
     * @param int[] $blockIds
     *
     * @return list<\c975L\UiBundle\Contract\HasBlocksInterface>
     */
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->select('o, ob')
            ->leftJoin('o.blocks', 'ob')
            ->andWhere('EXISTS (SELECT b.id FROM ' . Block::class . ' b WHERE b MEMBER OF o.blocks AND b.id IN (:blockIds))')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }
}
