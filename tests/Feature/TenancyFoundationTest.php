<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenancyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Platform Tenant',
            'slug' => 'acme',
            'subdomain' => 'acme',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Acme Platform Tenant',
            'slug' => 'acme',
        ]);
    }

    public function test_branch_can_be_created_for_a_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Branch Tenant',
            'slug' => 'branch-tenant',
        ]);

        $branch = $tenant->branches()->create([
            'name' => 'Main Branch',
            'slug' => 'main',
        ]);

        $this->assertInstanceOf(Branch::class, $branch);
        $this->assertTrue($tenant->branches()->whereKey($branch->id)->exists());
    }

    public function test_settings_can_be_stored_for_a_tenant_and_branch(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Settings Tenant',
            'slug' => 'settings-tenant',
        ]);

        $branch = $tenant->branches()->create([
            'name' => 'Main Branch',
            'slug' => 'main',
        ]);

        $setting = TenantSetting::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'key' => 'platform.timezone',
            'value' => ['timezone' => 'Europe/London'],
        ]);

        $this->assertSame(['timezone' => 'Europe/London'], $setting->fresh()->value);
    }

    public function test_tenant_context_can_be_resolved_from_request_headers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Resolved Tenant',
            'slug' => 'resolved',
        ]);

        $branch = $tenant->branches()->create([
            'name' => 'Resolved Branch',
            'slug' => 'resolved-branch',
        ]);

        $request = Request::create('/tenant-aware', 'GET', [], [], [], [
            'HTTP_X_TENANT' => 'resolved',
            'HTTP_X_BRANCH' => 'resolved-branch',
        ]);

        $resolver = app(TenantResolver::class);

        $this->assertTrue($tenant->is($resolver->resolveTenant($request)));
        $this->assertTrue($branch->is($resolver->resolveBranch($request, $tenant)));
    }

    public function test_tenant_middleware_sets_current_tenant_and_branch_helpers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Middleware Tenant',
            'slug' => 'middleware-tenant',
        ]);

        $branch = $tenant->branches()->create([
            'name' => 'Middleware Branch',
            'slug' => 'middleware-branch',
        ]);

        Route::middleware('tenant.required')->get('/tenant-context-test', function () {
            return response()->json([
                'tenant_id' => current_tenant()?->id,
                'branch_id' => current_branch()?->id,
            ]);
        });

        $this->getJson('/tenant-context-test', [
            'X-Tenant' => 'middleware-tenant',
            'X-Branch' => 'middleware-branch',
        ])->assertOk()->assertJson([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_tenant_required_middleware_blocks_missing_tenant_context(): void
    {
        Route::middleware('tenant.required')->get('/tenant-required-test', function () {
            return response()->json(['reachable' => true]);
        });

        $this->getJson('/tenant-required-test')->assertNotFound();
    }
}
