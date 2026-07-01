<?php

namespace Tests\Feature;

use App\Models\ReleaseNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Releases\MaintenanceModeService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseUpdateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_note_can_be_created(): void
    {
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner)
            ->post('/platform/releases', [
                'version' => '1.2.3',
                'title' => 'Update ready',
                'body' => 'Deployment notes for vendors.',
                'is_published' => '1',
            ])
            ->assertRedirect('/platform/releases');

        $releaseNote = ReleaseNote::query()->firstOrFail();

        $this->assertSame('1.2.3', $releaseNote->version);
        $this->assertTrue($releaseNote->is_published);
        $this->assertNotNull($releaseNote->published_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::RELEASE_NOTE_CREATED,
            'auditable_type' => ReleaseNote::class,
            'auditable_id' => $releaseNote->id,
        ]);

        [$tenant, $admin] = $this->tenantAdminFixture();

        $this->actingAs($admin)
            ->get('/tenant/release-notes')
            ->assertOk()
            ->assertSee('Update ready')
            ->assertSee('Version 1.2.3');
    }

    public function test_version_shown_in_footer_admin(): void
    {
        [$tenant, $admin] = $this->tenantAdminFixture();

        $this->actingAs($admin)
            ->get('/tenant/admin')
            ->assertOk()
            ->assertSee('Version '.config('app.version'));

        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner)
            ->get('/platform')
            ->assertOk()
            ->assertSee('Version '.config('app.version'));
    }

    public function test_health_check_works(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'Tong POS')
            ->assertJsonPath('version', config('app.version'))
            ->assertJsonPath('checks.app.status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok');

        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner)
            ->get('/platform/health')
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Version '.config('app.version'));
    }

    public function test_maintenance_page_works(): void
    {
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);
        app(MaintenanceModeService::class)->set(false, 'Release maintenance is disabled.', $platformOwner);

        $this->actingAs($platformOwner)
            ->get('/platform/maintenance')
            ->assertOk()
            ->assertSee('Maintenance')
            ->assertSee('Disabled');

        $this->actingAs($platformOwner)
            ->post('/platform/maintenance', [
                'enabled' => '1',
                'message' => 'Update in progress.',
            ])
            ->assertRedirect('/platform/maintenance');

        $this->actingAs($platformOwner)
            ->get('/platform/maintenance')
            ->assertOk()
            ->assertSee('Enabled')
            ->assertSee('Update in progress.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformOwner->id,
            'event' => AuditLogService::MAINTENANCE_MODE_TOGGLED,
        ]);
    }

    private function tenantAdminFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Release Tenant',
            'slug' => 'release-tenant-'.uniqid(),
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);

        return [$tenant, $admin];
    }
}
