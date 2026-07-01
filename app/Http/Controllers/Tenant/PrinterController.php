<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\Printing\PrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class PrinterController extends Controller
{
    public function index(): View
    {
        return view('tenant.printers.index', [
            'printers' => Printer::query()
                ->forTenant(current_tenant())
                ->with('branch:id,name')
                ->orderBy('name')
                ->get(),
            'jobs' => PrintJob::query()
                ->forTenant(current_tenant())
                ->with('printer:id,name,type')
                ->latest()
                ->limit(20)
                ->get(),
            'types' => Printer::TYPES,
            'roles' => Printer::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Printer::query()->create(array_merge($this->validatedPayload($request), [
            'tenant_id' => current_tenant()->id,
        ]));

        return redirect()->route('tenant.printers.index')->with('status', 'Printer created.');
    }

    public function update(Request $request, Printer $printer): RedirectResponse
    {
        $this->authorizeTenantPrinter($printer);

        $printer->update($this->validatedPayload($request));

        return redirect()->route('tenant.printers.index')->with('status', 'Printer updated.');
    }

    public function test(Printer $printer, PrintService $printing): RedirectResponse
    {
        $this->authorizeTenantPrinter($printer);

        $printing->createTestJob($printer);

        return redirect()->route('tenant.printers.index')->with('status', 'Test print queued.');
    }

    public function connectionTest(Printer $printer, PrintService $printing): RedirectResponse
    {
        $this->authorizeTenantPrinter($printer);

        try {
            $printing->testConnection($printer);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('tenant.printers.index')
                ->withErrors(['printer' => $exception->getMessage()]);
        }

        return redirect()->route('tenant.printers.index')->with('status', 'Printer connection succeeded.');
    }

    public function retry(PrintJob $job, PrintService $printing): RedirectResponse
    {
        abort_unless($job->tenant_id === current_tenant()->id, 404);
        abort_unless($job->status === PrintJob::STATUS_FAILED, 422, 'Only failed print jobs can be retried.');

        $printing->retry($job);

        return redirect()->route('tenant.printers.index')->with('status', 'Print job queued for retry.');
    }

    private function validatedPayload(Request $request): array
    {
        $payload = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', current_tenant()->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Printer::TYPES)],
            'role' => ['required', Rule::in(Printer::ROLES)],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_size' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload['is_active'] = (bool) ($payload['is_active'] ?? false);
        $payload['connection_settings'] = [
            'ip_address' => $payload['ip_address'] ?? null,
            'port' => $payload['port'] ?? null,
            'paper_size' => $payload['paper_size'],
        ];
        $payload['kitchen_category_routes'] = [
            'enabled' => false,
            'routes' => [],
        ];

        return $payload;
    }

    private function authorizeTenantPrinter(Printer $printer): void
    {
        abort_unless($printer->tenant_id === current_tenant()->id, 404);
    }
}
