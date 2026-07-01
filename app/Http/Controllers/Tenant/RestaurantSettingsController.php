<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\Settings\RestaurantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestaurantSettingsController extends Controller
{
    public function edit(RestaurantSettingsService $settingsService, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($tenant);

        return view('tenant.settings.restaurant', [
            'tenant' => $tenant,
            'settings' => $settingsService->get($tenant),
            'submitRoute' => $this->submitRoute($tenant),
        ]);
    }

    public function update(
        Request $request,
        RestaurantSettingsService $settingsService,
        AuditLogService $auditLog,
        ?Tenant $tenant = null
    ): RedirectResponse {
        $tenant = $this->resolveTenant($tenant);
        $before = $settingsService->get($tenant);

        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:255'],
            'logo_text' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'currency_code' => ['required', 'string', 'size:3'],
            'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'table_count' => ['required', 'integer', 'min:0', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'theme_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_upload_path'] = $request->file('logo')->store("tenant-logos/{$tenant->id}", 'public');
        }

        unset($data['logo']);

        $setting = $settingsService->save($tenant, $data);
        $after = $settingsService->get($tenant);

        $auditLog->settingsChanged($setting, [
            'setting_key' => RestaurantSettingsService::KEY,
            'before' => $before,
            'after' => $after,
        ]);

        return redirect()
            ->route($this->redirectRouteName($tenant), $this->redirectRouteParameters($tenant))
            ->with('status', 'Restaurant settings saved.');
    }

    private function resolveTenant(?Tenant $tenant): Tenant
    {
        $tenant ??= current_tenant();

        abort_unless($tenant, 404, 'Tenant context is required.');

        return $tenant;
    }

    private function submitRoute(Tenant $tenant): string
    {
        if (request()->routeIs('platform.*')) {
            return route('platform.vendors.settings.update', $tenant);
        }

        return route('tenant.settings.restaurant.update');
    }

    private function redirectRouteName(Tenant $tenant): string
    {
        return request()->routeIs('platform.*')
            ? 'platform.vendors.settings.edit'
            : 'tenant.settings.restaurant.edit';
    }

    private function redirectRouteParameters(Tenant $tenant): array
    {
        return request()->routeIs('platform.*') ? [$tenant] : [];
    }
}
