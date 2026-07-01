<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        return view('platform.vendors.index', [
            'vendors' => Tenant::query()
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('platform.vendors.create', [
            'vendor' => new Tenant(['is_active' => true]),
        ]);
    }

    public function store(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $this->validatedVendorData($request);

        $vendor = Tenant::query()->create($data);

        $auditLog->vendorCreated($vendor, [
            'attributes' => Arr::only($vendor->fresh()->toArray(), ['name', 'slug', 'domain', 'subdomain', 'is_active']),
        ]);

        return redirect()
            ->route('platform.vendors.show', $vendor)
            ->with('status', 'Vendor created.');
    }

    public function show(Tenant $vendor): View
    {
        return view('platform.vendors.show', [
            'vendor' => $vendor,
            'auditLogs' => $vendor->auditLogs()
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(Tenant $vendor): View
    {
        return view('platform.vendors.edit', [
            'vendor' => $vendor,
        ]);
    }

    public function update(Request $request, Tenant $vendor, AuditLogService $auditLog): RedirectResponse
    {
        $before = Arr::only($vendor->toArray(), ['name', 'slug', 'domain', 'subdomain', 'is_active']);
        $vendor->update($this->validatedVendorData($request, $vendor));
        $after = Arr::only($vendor->fresh()->toArray(), ['name', 'slug', 'domain', 'subdomain', 'is_active']);

        $auditLog->vendorUpdated($vendor, [
            'before' => $before,
            'after' => $after,
        ]);

        return redirect()
            ->route('platform.vendors.show', $vendor)
            ->with('status', 'Vendor updated.');
    }

    public function suspend(Tenant $vendor, AuditLogService $auditLog): RedirectResponse
    {
        $wasActive = $vendor->is_active;
        $vendor->forceFill(['is_active' => false])->save();

        $auditLog->vendorSuspended($vendor, [
            'before' => ['is_active' => $wasActive],
            'after' => ['is_active' => false],
        ]);

        return back()->with('status', 'Vendor suspended.');
    }

    public function reactivate(Tenant $vendor, AuditLogService $auditLog): RedirectResponse
    {
        $wasActive = $vendor->is_active;
        $vendor->forceFill(['is_active' => true])->save();

        $auditLog->vendorReactivated($vendor, [
            'before' => ['is_active' => $wasActive],
            'after' => ['is_active' => true],
        ]);

        return back()->with('status', 'Vendor reactivated.');
    }

    private function validatedVendorData(Request $request, ?Tenant $vendor = null): array
    {
        $vendorId = $vendor?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($vendorId)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($vendorId)],
            'subdomain' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'subdomain')->ignore($vendorId)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
