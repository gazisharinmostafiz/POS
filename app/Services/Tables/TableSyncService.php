<?php

namespace App\Services\Tables;

use App\Models\RestaurantTable;
use App\Models\Tenant;

class TableSyncService
{
    public function syncTenantTables(Tenant $tenant, int $tableCount): void
    {
        $tableCount = max(0, $tableCount);

        for ($number = 1; $number <= $tableCount; $number++) {
            RestaurantTable::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => null,
                    'number' => $number,
                ],
                [
                    'label' => "Table {$number}",
                    'is_active' => true,
                ]
            );
        }

        RestaurantTable::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('branch_id')
            ->where('number', '>', $tableCount)
            ->update([
                'is_active' => false,
                'status' => RestaurantTable::STATUS_FREE,
            ]);
    }
}
