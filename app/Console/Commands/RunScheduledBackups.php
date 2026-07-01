<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\Tenant;
use App\Services\Backups\BackupService;
use App\Services\FeatureGate;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    protected $signature = 'backups:run {--platform : Include a full platform backup} {--encrypted : Encrypt generated backups}';

    protected $description = 'Run scheduled tenant and optional platform backups.';

    public function handle(BackupService $backups, FeatureGate $features): int
    {
        Tenant::query()
            ->where('is_active', true)
            ->with('plan.features')
            ->get()
            ->each(function (Tenant $tenant) use ($backups, $features) {
                if ($features->tenantHasFeature($tenant, BackupService::featureKey())) {
                    $backup = $backups->createTenantBackup($tenant, Backup::TYPE_FULL_TENANT, null, (bool) $this->option('encrypted'));
                    $this->line("Tenant {$tenant->id} backup {$backup->status}: {$backup->filename}");
                }
            });

        if ($this->option('platform')) {
            $backup = $backups->createPlatformBackup(Backup::TYPE_FULL_PLATFORM, null, (bool) $this->option('encrypted'));
            $this->line("Platform backup {$backup->status}: {$backup->filename}");
        }

        return self::SUCCESS;
    }
}
