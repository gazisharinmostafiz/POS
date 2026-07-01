<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Backups\BackupService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_backup_creates_file(): void
    {
        Storage::fake('backups');
        [$tenant, $admin] = $this->backupTenant();

        $backup = app(BackupService::class)->createTenantBackup($tenant, Backup::TYPE_DATABASE, $admin);

        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status);
        $this->assertNotNull($backup->path);
        Storage::disk('backups')->assertExists($backup->path);
    }

    public function test_backup_log_created(): void
    {
        Storage::fake('backups');
        [$tenant, $admin] = $this->backupTenant();

        $this->actingAs($admin)
            ->post('/tenant/backups', [
                'type' => Backup::TYPE_DATABASE,
            ])
            ->assertRedirect();

        $backup = Backup::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::BACKUP_CREATED,
            'auditable_id' => $backup->id,
            'auditable_type' => Backup::class,
        ]);
    }

    public function test_unauthorized_download_blocked(): void
    {
        Storage::fake('backups');
        [$tenantA, $adminA] = $this->backupTenant('Tenant A');
        [, $adminB] = $this->backupTenant('Tenant B');
        $backup = app(BackupService::class)->createTenantBackup($tenantA, Backup::TYPE_DATABASE, $adminA);

        $this->actingAs($adminB)
            ->get("/tenant/backups/{$backup->id}/download")
            ->assertNotFound();
    }

    public function test_backup_file_has_checksum(): void
    {
        Storage::fake('backups');
        [$tenant, $admin] = $this->backupTenant();

        $backup = app(BackupService::class)->createTenantBackup($tenant, Backup::TYPE_FULL_TENANT, $admin);
        $contents = Storage::disk('backups')->get($backup->path);

        $this->assertNotNull($backup->checksum);
        $this->assertSame(hash('sha256', $contents), $backup->checksum);
        $this->assertGreaterThan(0, $backup->size_bytes);
    }

    private function backupTenant(string $name = 'Backup Tenant'): array
    {
        $plan = Plan::query()->create([
            'name' => $name.' Plan',
            'slug' => 'backup-plan-'.uniqid(),
            'is_active' => true,
        ]);
        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'feature_key' => BackupService::featureKey(),
            'enabled' => true,
        ]);
        $tenant = Tenant::query()->create([
            'name' => $name.' '.uniqid(),
            'slug' => 'backup-tenant-'.uniqid(),
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);

        return [$tenant, $admin];
    }
}
