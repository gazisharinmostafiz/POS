<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $starter = $this->plan('Starter', 'starter', 'Entry plan for a single restaurant operation.', 10);
        $this->feature($starter, 'pos.waiter', 'Waiter POS', null);
        $this->feature($starter, 'pos.counter', 'Counter screen', null);
        $this->feature($starter, 'settings.restaurant', 'Restaurant settings', null);
        $this->feature($starter, 'limits.branches', 'Branch limit', 1);
        $this->feature($starter, 'limits.tables', 'Table limit', 20);

        $pro = $this->plan('Pro', 'pro', 'Expanded plan for multi-screen operations.', 20);
        $this->feature($pro, 'pos.waiter', 'Waiter POS', null);
        $this->feature($pro, 'pos.counter', 'Counter screen', null);
        $this->feature($pro, 'pos.kitchen', 'Kitchen screen', null);
        $this->feature($pro, 'settings.restaurant', 'Restaurant settings', null);
        $this->feature($pro, 'reports.advanced', 'Advanced reports', null);
        $this->feature($pro, 'limits.branches', 'Branch limit', 3);
        $this->feature($pro, 'limits.tables', 'Table limit', 80);

        $premium = $this->plan('Premium', 'premium', 'Full platform plan for advanced restaurant operations.', 30);
        $this->feature($premium, 'pos.waiter', 'Waiter POS', null);
        $this->feature($premium, 'pos.counter', 'Counter screen', null);
        $this->feature($premium, 'pos.kitchen', 'Kitchen screen', null);
        $this->feature($premium, 'settings.restaurant', 'Restaurant settings', null);
        $this->feature($premium, 'reports.advanced', 'Advanced reports', null);
        $this->feature($premium, 'integrations.accounting', 'Accounting integrations', null);
        $this->feature($premium, 'support.priority', 'Priority support', null);
        $this->feature($premium, 'limits.branches', 'Branch limit', null);
        $this->feature($premium, 'limits.tables', 'Table limit', null);

        $kitchenDisplay = $this->addon('Kitchen Display', 'kitchen-display', 'Adds kitchen screen access to eligible tenants.');
        $this->addonFeature($kitchenDisplay, 'pos.kitchen', 'Kitchen screen', null);

        $advancedReports = $this->addon('Advanced Reports', 'advanced-reports', 'Adds advanced reporting access.');
        $this->addonFeature($advancedReports, 'reports.advanced', 'Advanced reports', null);

        $extraTables = $this->addon('Extra Tables', 'extra-tables', 'Raises table limits for larger restaurants.');
        $this->addonFeature($extraTables, 'limits.tables', 'Expanded table limit', 100);

        $prioritySupport = $this->addon('Priority Support', 'priority-support', 'Adds premium support entitlements.');
        $this->addonFeature($prioritySupport, 'support.priority', 'Priority support', null);
    }

    private function plan(string $name, string $slug, string $description, int $sortOrder): Plan
    {
        return Plan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]
        );
    }

    private function feature(Plan $plan, string $key, string $name, ?int $limit): void
    {
        $plan->features()->updateOrCreate(
            ['feature_key' => $key],
            [
                'name' => $name,
                'enabled' => true,
                'limit' => $limit,
            ]
        );
    }

    private function addon(string $name, string $slug, string $description): Addon
    {
        return Addon::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }

    private function addonFeature(Addon $addon, string $key, string $name, ?int $limit): void
    {
        $addon->features()->updateOrCreate(
            ['feature_key' => $key],
            [
                'name' => $name,
                'enabled' => true,
                'limit' => $limit,
            ]
        );
    }
}
