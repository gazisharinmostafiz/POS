<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_on_starter_cannot_access_pro_only_feature(): void
    {
        $this->seedPlans();

        $tenant = Tenant::query()->create([
            'name' => 'Starter Tenant',
            'slug' => 'starter-tenant',
            'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
            'subscription_status' => 'active',
        ]);

        $gate = app(FeatureGate::class);

        $this->assertTrue($gate->tenantHasFeature($tenant, 'pos.waiter'));
        $this->assertFalse($gate->tenantHasFeature($tenant, 'reports.advanced'));
        $this->assertFalse(tenantHasFeature('reports.advanced', $tenant));
    }

    public function test_tenant_addon_enables_feature(): void
    {
        $this->seedPlans();

        $tenant = Tenant::query()->create([
            'name' => 'Addon Tenant',
            'slug' => 'addon-tenant',
            'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
            'subscription_status' => 'active',
        ]);

        TenantAddon::query()->create([
            'tenant_id' => $tenant->id,
            'addon_id' => Addon::query()->where('slug', 'advanced-reports')->value('id'),
            'status' => TenantAddon::STATUS_ACTIVE,
        ]);

        $this->assertTrue(app(FeatureGate::class)->tenantHasFeature($tenant, 'reports.advanced'));
    }

    public function test_disabled_subscription_blocks_premium_features(): void
    {
        $this->seedPlans();

        $tenant = Tenant::query()->create([
            'name' => 'Disabled Tenant',
            'slug' => 'disabled-tenant',
            'plan_id' => Plan::query()->where('slug', 'premium')->value('id'),
            'subscription_status' => 'disabled',
        ]);

        $this->assertFalse(app(FeatureGate::class)->tenantHasFeature($tenant, 'support.priority'));
    }

    public function test_plan_limit_checker_uses_plan_and_addon_limits(): void
    {
        $this->seedPlans();

        $tenant = Tenant::query()->create([
            'name' => 'Limit Tenant',
            'slug' => 'limit-tenant',
            'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
            'subscription_status' => 'active',
        ]);

        $gate = app(FeatureGate::class);

        $this->assertTrue($gate->withinLimit($tenant, 'limits.tables', 19));
        $this->assertFalse($gate->withinLimit($tenant, 'limits.tables', 20));

        TenantAddon::query()->create([
            'tenant_id' => $tenant->id,
            'addon_id' => Addon::query()->where('slug', 'extra-tables')->value('id'),
            'status' => TenantAddon::STATUS_ACTIVE,
        ]);

        $this->assertTrue($gate->withinLimit($tenant, 'limits.tables', 99));
        $this->assertFalse($gate->withinLimit($tenant, 'limits.tables', 100));
    }

    private function seedPlans(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }
}
