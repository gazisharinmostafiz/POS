<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Reports\TenantReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardReportController extends Controller
{
    public function index(Request $request, TenantReportService $reports): View
    {
        $filters = $this->filters($request);

        return view('areas.tenant-admin', [
            'reportData' => $reports->reports(current_tenant(), $filters),
            'filterOptions' => $reports->filterOptions(current_tenant()),
            'filters' => $filters,
        ]);
    }

    public function data(Request $request, TenantReportService $reports): JsonResponse
    {
        return response()->json($reports->reports(current_tenant(), $this->filters($request)));
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'in:today,yesterday,this_week,this_month,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'in:cash,card'],
            'waiter_id' => ['nullable', 'integer'],
            'cashier_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'in:table,takeaway,walk_in'],
        ]);
    }
}
