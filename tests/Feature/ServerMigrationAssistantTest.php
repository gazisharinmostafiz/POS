<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\ServerMigration;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerMigrationAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_migration_package(): void
    {
        Storage::fake('backups');
        $owner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($owner)
            ->post('/platform/migrations')
            ->assertRedirect();

        $migration = ServerMigration::query()->firstOrFail();

        $this->assertSame(ServerMigration::STATUS_COMPLETED, $migration->status);
        $this->assertNotNull($migration->backup_id);
        $this->assertNotNull($migration->path);
        Storage::disk('backups')->assertExists($migration->path);

        $package = json_decode(Storage::disk('backups')->get($migration->path), true);
        $this->assertSame($migration->version, $package['manifest']['version']);
        $this->assertArrayHasKey('database_dump', $package);
        $this->assertArrayHasKey('uploaded_files', $package);
        $this->assertArrayHasKey('invoice_files', $package);
        $this->assertArrayHasKey('env_example_checklist', $package);
        $this->assertStringContainsString('Restore Guide', $package['restore_guide_markdown']);
        $this->assertDatabaseHas('backups', [
            'id' => $migration->backup_id,
            'type' => Backup::TYPE_FULL_PLATFORM,
            'status' => Backup::STATUS_COMPLETED,
        ]);
    }

    public function test_package_has_checksum(): void
    {
        Storage::fake('backups');
        $owner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($owner)->post('/platform/migrations');

        $migration = ServerMigration::query()->firstOrFail();
        $contents = Storage::disk('backups')->get($migration->path);

        $this->assertNotNull($migration->checksum);
        $this->assertSame(hash('sha256', $contents), $migration->checksum);
        $this->assertGreaterThan(0, $migration->size_bytes);
    }

    public function test_unauthorized_user_blocked(): void
    {
        $admin = User::factory()->create(['role' => Roles::ADMIN]);

        $this->actingAs($admin)
            ->get('/platform/migrations')
            ->assertForbidden();
    }

    public function test_migration_log_created(): void
    {
        Storage::fake('backups');
        $owner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($owner)
            ->post('/platform/migrations')
            ->assertRedirect();

        $migration = ServerMigration::query()->firstOrFail();

        $this->assertDatabaseHas('server_migrations', [
            'id' => $migration->id,
            'status' => ServerMigration::STATUS_COMPLETED,
            'created_by' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::MIGRATION_PACKAGE_CREATED,
            'auditable_type' => ServerMigration::class,
            'auditable_id' => $migration->id,
        ]);
    }
}
