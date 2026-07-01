<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Releases\MaintenanceModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function show(MaintenanceModeService $maintenanceModeService): View
    {
        return view('platform.maintenance', [
            'maintenance' => $maintenanceModeService->status(),
        ]);
    }

    public function update(
        Request $request,
        MaintenanceModeService $maintenanceModeService,
        AuditLogService $auditLogService
    ): RedirectResponse {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $maintenanceModeService->set(
            (bool) $validated['enabled'],
            $validated['message'] ?? null,
            $request->user()
        );

        $auditLogService->record(AuditLogService::MAINTENANCE_MODE_TOGGLED, null, [
            'enabled' => $status['enabled'],
            'message' => $status['message'],
        ]);

        return redirect()->route('platform.maintenance.show')->with('status', 'Maintenance status updated.');
    }
}
