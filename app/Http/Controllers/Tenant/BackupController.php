<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\AuditLogService;
use App\Services\Backups\BackupService;
use App\Services\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(FeatureGate $features)
    {
        abort_unless($features->tenantHasFeature(current_tenant(), BackupService::featureKey()), 403);

        return view('tenant.backups.index', [
            'backups' => Backup::query()
                ->forTenant(current_tenant())
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, BackupService $backups, AuditLogService $auditLog, FeatureGate $features): RedirectResponse
    {
        abort_unless($features->tenantHasFeature(current_tenant(), BackupService::featureKey()), 403);

        $payload = $request->validate([
            'type' => ['required', Rule::in([
                Backup::TYPE_DATABASE,
                Backup::TYPE_UPLOADS,
                Backup::TYPE_INVOICES,
                Backup::TYPE_FULL_TENANT,
            ])],
            'encrypted' => ['nullable', 'boolean'],
        ]);

        $backup = $backups->createTenantBackup(
            current_tenant(),
            $payload['type'],
            $request->user(),
            (bool) ($payload['encrypted'] ?? false)
        );

        $auditLog->backupChanged(AuditLogService::BACKUP_CREATED, $backup, [
            'status' => $backup->status,
            'type' => $backup->type,
            'checksum' => $backup->checksum,
        ]);

        return back()->with('status', 'Backup job completed with status: '.$backup->status.'.');
    }

    public function download(Backup $backup, AuditLogService $auditLog, FeatureGate $features): StreamedResponse
    {
        abort_unless($features->tenantHasFeature(current_tenant(), BackupService::featureKey()), 403);
        abort_unless($backup->tenant_id === current_tenant()->id, 404);
        abort_unless($backup->status === Backup::STATUS_COMPLETED && $backup->path, 404);
        abort_unless($backup->disk === 'backups' && ! str_contains($backup->path, '..'), 404);
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);

        $auditLog->backupChanged(AuditLogService::BACKUP_DOWNLOADED, $backup, [
            'checksum' => $backup->checksum,
        ]);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    public function restore(Backup $backup, AuditLogService $auditLog, FeatureGate $features): RedirectResponse
    {
        abort_unless($features->tenantHasFeature(current_tenant(), BackupService::featureKey()), 403);
        abort_unless($backup->tenant_id === current_tenant()->id, 404);

        $auditLog->backupChanged(AuditLogService::BACKUP_RESTORED, $backup, [
            'placeholder' => true,
            'message' => 'Restore workflow audit placeholder. No data was mutated.',
        ]);

        return back()->with('status', 'Restore request logged. Automated restore is not enabled yet.');
    }
}
