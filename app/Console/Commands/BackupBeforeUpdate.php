<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\AuditLogService;
use App\Services\Backups\BackupService;
use Illuminate\Console\Command;

class BackupBeforeUpdate extends Command
{
    protected $signature = 'release:backup-before-update {--encrypted : Encrypt the backup package}';

    protected $description = 'Create a full platform backup before applying a production update.';

    public function handle(BackupService $backupService, AuditLogService $auditLogService): int
    {
        $backup = $backupService->createPlatformBackup(
            Backup::TYPE_FULL_PLATFORM,
            null,
            (bool) $this->option('encrypted')
        );

        $auditLogService->record(AuditLogService::UPDATE_BACKUP_CREATED, $backup, [
            'status' => $backup->status,
            'checksum' => $backup->checksum,
            'version' => config('app.version'),
        ]);

        if ($backup->status !== Backup::STATUS_COMPLETED) {
            $this->error('Backup failed: '.($backup->last_error ?: 'Unknown error.'));

            return self::FAILURE;
        }

        $this->info('Backup completed before update: '.$backup->filename);
        $this->line('Checksum: '.$backup->checksum);

        return self::SUCCESS;
    }
}
