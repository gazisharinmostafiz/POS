<?php

namespace App\Console\Commands;

use App\Services\Releases\MaintenanceModeService;
use Illuminate\Console\Command;

class ReleaseMaintenanceMode extends Command
{
    protected $signature = 'release:maintenance {state : on, off, or status} {--message= : Optional maintenance message}';

    protected $description = 'Manage the release maintenance status shown in the platform update page.';

    public function handle(MaintenanceModeService $maintenanceModeService): int
    {
        $state = strtolower((string) $this->argument('state'));

        if ($state === 'status') {
            $status = $maintenanceModeService->status();
            $this->line($status['enabled'] ? 'enabled' : 'disabled');
            $this->line($status['message']);

            return self::SUCCESS;
        }

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be one of: on, off, status.');

            return self::INVALID;
        }

        $status = $maintenanceModeService->set(
            $state === 'on',
            $this->option('message') ?: null
        );

        $this->info('Release maintenance '.($status['enabled'] ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}
