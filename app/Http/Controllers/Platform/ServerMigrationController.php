<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\MigrationRemoteCredential;
use App\Models\ServerMigration;
use App\Services\AuditLogService;
use App\Services\Migrations\ServerMigrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerMigrationController extends Controller
{
    public function index()
    {
        return view('platform.migrations.index', [
            'migrations' => ServerMigration::query()->latest()->get(),
            'credentials' => MigrationRemoteCredential::query()->latest()->get(),
            'preflight' => [
                'Full platform backup will be created first.',
                'Package includes database dump, uploads, invoices, .env.example checklist, checksum, and restore guide.',
                'Production secrets are excluded.',
                'Remote SSH/SFTP transfer is a placeholder.',
                'Restore requires explicit confirmation.',
            ],
        ]);
    }

    public function store(Request $request, ServerMigrationService $service, AuditLogService $auditLog): RedirectResponse
    {
        $migration = $service->generatePackage($request->user());

        $auditLog->migrationChanged(AuditLogService::MIGRATION_PACKAGE_CREATED, $migration, [
            'status' => $migration->status,
            'checksum' => $migration->checksum,
            'backup_id' => $migration->backup_id,
        ]);

        return back()->with('status', 'Migration package completed with status: '.$migration->status.'.');
    }

    public function download(ServerMigration $migration, AuditLogService $auditLog): StreamedResponse
    {
        abort_unless($migration->status === ServerMigration::STATUS_COMPLETED && $migration->path, 404);
        abort_unless($migration->disk === 'backups' && ! str_contains($migration->path, '..'), 404);
        abort_unless(Storage::disk($migration->disk)->exists($migration->path), 404);

        $auditLog->migrationChanged(AuditLogService::MIGRATION_PACKAGE_DOWNLOADED, $migration, [
            'checksum' => $migration->checksum,
        ]);

        return Storage::disk($migration->disk)->download($migration->path, $migration->filename);
    }

    public function restore(Request $request, ServerMigration $migration, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
        ]);

        $auditLog->migrationChanged(AuditLogService::MIGRATION_RESTORE_CONFIRMED, $migration, [
            'confirmation' => $payload['confirmation'],
            'placeholder' => true,
            'message' => 'Server migration restore was confirmed but no automated restore was executed.',
        ]);

        return back()->with('status', 'Restore confirmation logged. Automated restore is not enabled yet.');
    }

    public function credentials(Request $request, ServerMigrationService $service, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'auth_type' => ['nullable', 'in:password,private_key'],
            'password' => ['nullable', 'string', 'max:2000'],
            'private_key' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $credential = $service->saveRemoteCredentials($request->user(), $payload);

        $auditLog->migrationChanged(AuditLogService::MIGRATION_CREDENTIALS_SAVED, $credential, [
            'name' => $credential->name,
            'host' => $credential->host,
            'secrets_visible' => false,
        ]);

        return back()->with('status', 'Remote migration credentials saved encrypted. Secrets are not shown after save.');
    }

    public function maintenance(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $auditLog->record(AuditLogService::MAINTENANCE_MODE_TOGGLED, null, [
            'enabled' => (bool) $payload['enabled'],
            'placeholder' => true,
            'message' => 'Maintenance mode toggle placeholder. No artisan down/up command was executed.',
        ]);

        return back()->with('status', 'Maintenance mode placeholder logged.');
    }
}
