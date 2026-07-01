<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\AuditLogService;
use App\Services\Backups\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index()
    {
        return view('platform.backups.index', [
            'backups' => Backup::query()
                ->where('scope', Backup::SCOPE_PLATFORM)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, BackupService $backups, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $request->validate([
            'type' => ['required', Rule::in([Backup::TYPE_DATABASE, Backup::TYPE_FULL_PLATFORM])],
            'encrypted' => ['nullable', 'boolean'],
        ]);

        $backup = $backups->createPlatformBackup(
            $payload['type'],
            $request->user(),
            (bool) ($payload['encrypted'] ?? false)
        );

        $auditLog->backupChanged(AuditLogService::BACKUP_CREATED, $backup, [
            'status' => $backup->status,
            'type' => $backup->type,
            'checksum' => $backup->checksum,
        ]);

        return back()->with('status', 'Platform backup completed with status: '.$backup->status.'.');
    }

    public function download(Backup $backup, AuditLogService $auditLog): StreamedResponse
    {
        abort_unless($backup->scope === Backup::SCOPE_PLATFORM, 404);
        abort_unless($backup->status === Backup::STATUS_COMPLETED && $backup->path, 404);
        abort_unless($backup->disk === 'backups' && ! str_contains($backup->path, '..'), 404);
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);

        $auditLog->backupChanged(AuditLogService::BACKUP_DOWNLOADED, $backup, [
            'checksum' => $backup->checksum,
        ]);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    public function restore(Backup $backup, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($backup->scope === Backup::SCOPE_PLATFORM, 404);

        $auditLog->backupChanged(AuditLogService::BACKUP_RESTORED, $backup, [
            'placeholder' => true,
            'message' => 'Platform restore workflow audit placeholder. No data was mutated.',
        ]);

        return back()->with('status', 'Platform restore request logged. Automated restore is not enabled yet.');
    }
}
