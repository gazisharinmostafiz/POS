<?php

namespace App\Services\Releases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    public function report(): array
    {
        $checks = [
            'app' => [
                'status' => 'ok',
                'service' => config('app.name'),
                'version' => config('app.version'),
                'environment' => app()->environment(),
            ],
            'database' => $this->check(fn () => DB::select('select 1')),
            'storage' => $this->check(function () {
                Storage::disk('local')->put('health/.probe', now()->toIso8601String());
                Storage::disk('local')->delete('health/.probe');
            }),
            'maintenance' => [
                'status' => app(MaintenanceModeService::class)->enabled() ? 'maintenance' : 'ok',
                'enabled' => app(MaintenanceModeService::class)->enabled(),
            ],
            'monitoring' => [
                'status' => 'ok',
                'error_tracking_configured' => filled(config('monitoring.error_tracking_dsn')),
                'health_endpoint' => config('monitoring.health_endpoint'),
            ],
        ];

        $status = collect($checks)
            ->contains(fn (array $check) => $check['status'] === 'fail') ? 'fail' : 'ok';

        return [
            'status' => $status,
            'service' => config('app.name'),
            'version' => config('app.version'),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function check(callable $callback): array
    {
        try {
            $callback();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
