<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\Releases\HealthCheckService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(): View
    {
        return view('platform.overview', [
            'totalVendors' => Tenant::query()->count(),
            'activeVendors' => Tenant::query()->where('is_active', true)->count(),
            'suspendedVendors' => Tenant::query()->where('is_active', false)->count(),
            'recentAuditLogs' => AuditLog::query()
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function health(HealthCheckService $healthCheckService): View
    {
        return view('platform.health', [
            'health' => $healthCheckService->report(),
        ]);
    }

    public function subscriptions(): View
    {
        return view('platform.subscriptions');
    }

    public function supportAccessLogs(): View
    {
        return view('platform.support-access-logs');
    }
}
