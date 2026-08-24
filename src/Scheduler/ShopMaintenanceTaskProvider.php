<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

// The commands this bundle needs run on a cadence: a site installing the shop gets them scheduled, one removing it stops running them, and neither has anything to edit in its own MaintenanceSchedule
class ShopMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Expired download copies, nightly
            new MaintenanceTask('# #(1-3) * * *', 'c975l:shop:downloads:delete'),
            // Product affinities, monthly: a full pass over the orders, too long to run nightly for what it changes
            new MaintenanceTask('# #(2-5) # * *', 'c975l:shop:affinity:calculate'),
            // Back-in-stock alerts, hourly and in batches: somebody told a day after the restock is told after the stock is gone again
            new MaintenanceTask('# * * * *', 'c975l:shop:stock-alerts:send'),
        ];
    }
}
