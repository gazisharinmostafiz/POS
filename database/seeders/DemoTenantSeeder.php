<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $starterPlan = Plan::query()->where('slug', 'starter')->first();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'poslab'],
            [
                'name' => 'PosLAB',
                'plan_id' => $starterPlan?->id,
                'subdomain' => 'poslab',
                'is_active' => true,
                'subscription_status' => 'active',
            ]
        );

        $tenant->forceFill([
            'plan_id' => $tenant->plan_id ?? $starterPlan?->id,
            'subscription_status' => $tenant->subscription_status ?: 'active',
        ])->save();

        $branch = $tenant->branches()->firstOrCreate(
            ['slug' => 'main'],
            [
                'name' => 'Main Branch',
                'is_active' => true,
            ]
        );

        $tenant->settings()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'key' => 'platform.locale',
            ],
            [
                'value' => ['locale' => 'en'],
            ]
        );
    }
}
