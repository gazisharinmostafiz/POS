<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_owner_can_access_platform_pages(): void
    {
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);
        $admin = User::factory()->create(['role' => Roles::ADMIN]);

        $this->actingAs($platformOwner)
            ->get('/platform')
            ->assertOk()
            ->assertSee('Platform overview');

        $this->actingAs($admin)
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_platform_owner_pages_render(): void
    {
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner);

        $this->get('/platform/vendors')->assertOk()->assertSee('Vendors');
        $this->get('/platform/vendors/create')->assertOk()->assertSee('Create vendor');
        $this->get('/platform/health')->assertOk()->assertSee('System health');
        $this->get('/platform/subscriptions')->assertOk()->assertSee('Subscription overview');
        $this->get('/platform/support-access-logs')->assertOk()->assertSee('Support access log');
    }

    public function test_platform_owner_can_create_edit_suspend_and_reactivate_vendor_with_audit_logs(): void
    {
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner);

        $this->post('/platform/vendors', [
            'name' => 'Vendor One',
            'slug' => 'vendor-one',
            'domain' => 'vendor-one.example.test',
            'subdomain' => 'vendor-one',
            'is_active' => '1',
        ])->assertRedirect();

        $vendor = Tenant::query()->where('slug', 'vendor-one')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformOwner->id,
            'event' => AuditLogService::VENDOR_CREATED,
            'auditable_type' => Tenant::class,
            'auditable_id' => $vendor->id,
        ]);

        $this->put("/platform/vendors/{$vendor->id}", [
            'name' => 'Vendor One Updated',
            'slug' => 'vendor-one-updated',
            'domain' => 'vendor-one-updated.example.test',
            'subdomain' => 'vendor-one-updated',
            'is_active' => '1',
        ])->assertRedirect("/platform/vendors/{$vendor->id}");

        $vendor->refresh();

        $this->assertSame('Vendor One Updated', $vendor->name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformOwner->id,
            'event' => AuditLogService::VENDOR_UPDATED,
            'auditable_type' => Tenant::class,
            'auditable_id' => $vendor->id,
        ]);

        $this->patch("/platform/vendors/{$vendor->id}/suspend")->assertRedirect();
        $this->assertFalse($vendor->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformOwner->id,
            'event' => AuditLogService::VENDOR_SUSPENDED,
            'auditable_type' => Tenant::class,
            'auditable_id' => $vendor->id,
        ]);

        $this->patch("/platform/vendors/{$vendor->id}/reactivate")->assertRedirect();
        $this->assertTrue($vendor->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformOwner->id,
            'event' => AuditLogService::VENDOR_REACTIVATED,
            'auditable_type' => Tenant::class,
            'auditable_id' => $vendor->id,
        ]);
    }

    public function test_vendor_details_are_platform_only(): void
    {
        $vendor = Tenant::query()->create([
            'name' => 'Private Vendor',
            'slug' => 'private-vendor',
        ]);
        $tenantUser = User::factory()->create([
            'tenant_id' => $vendor->id,
            'role' => Roles::ADMIN,
        ]);

        $this->actingAs($tenantUser)
            ->get("/platform/vendors/{$vendor->id}")
            ->assertForbidden();
    }
}
