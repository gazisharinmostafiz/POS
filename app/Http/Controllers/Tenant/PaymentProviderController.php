<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\Settings\PaymentProviderSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentProviderController extends Controller
{
    public function index(PaymentProviderSettingsService $providers): View
    {
        $tenant = current_tenant();

        abort_unless($tenant, 404);

        return view('tenant.settings.payment-providers.index', [
            'tenant' => $tenant,
            'providers' => $providers->listForTenant($tenant),
            'providerOptions' => PaymentProviderSettingsService::PROVIDERS,
        ]);
    }

    public function create(PaymentProviderSettingsService $providers): View
    {
        $tenant = current_tenant();

        abort_unless($tenant, 404);

        return view('tenant.settings.payment-providers.form', [
            'tenant' => $tenant,
            'providerAccount' => new ProviderAccountSetting(['is_active' => true, 'settings' => ['mode' => 'test']]),
            'providerOptions' => PaymentProviderSettingsService::PROVIDERS,
            'submitRoute' => route('tenant.settings.payment-providers.store'),
            'method' => 'POST',
        ]);
    }

    public function store(
        Request $request,
        PaymentProviderSettingsService $providers,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $tenant = current_tenant();

        abort_unless($tenant, 404);

        $data = $this->validated($request);
        $setting = $providers->create($tenant, $data);

        $auditLog->record(AuditLogService::SETTINGS_CHANGED, $setting, [
            'provider' => $setting->provider,
            'account_reference' => $setting->account_reference,
        ]);

        return redirect()
            ->route('tenant.settings.payment-providers.index')
            ->with('status', 'Payment gateway added.');
    }

    public function edit(ProviderAccountSetting $providerAccount, PaymentProviderSettingsService $providers): View
    {
        $tenant = current_tenant();

        abort_unless($tenant, 404);
        $providers->assertBelongsToTenant($providerAccount, $tenant);

        return view('tenant.settings.payment-providers.form', [
            'tenant' => $tenant,
            'providerAccount' => $providerAccount,
            'providerOptions' => PaymentProviderSettingsService::PROVIDERS,
            'submitRoute' => route('tenant.settings.payment-providers.update', $providerAccount),
            'method' => 'PUT',
        ]);
    }

    public function update(
        Request $request,
        ProviderAccountSetting $providerAccount,
        PaymentProviderSettingsService $providers,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $tenant = current_tenant();

        abort_unless($tenant, 404);
        $providers->assertBelongsToTenant($providerAccount, $tenant);

        $data = $this->validated($request, $providerAccount);
        $before = $providerAccount->only(['provider', 'account_reference', 'is_active']);
        $setting = $providers->update($providerAccount, $data);

        $auditLog->record(AuditLogService::SETTINGS_CHANGED, $setting, [
            'before' => $before,
            'after' => $setting->only(['provider', 'account_reference', 'is_active']),
        ]);

        return redirect()
            ->route('tenant.settings.payment-providers.index')
            ->with('status', 'Payment gateway updated.');
    }

    public function destroy(
        ProviderAccountSetting $providerAccount,
        PaymentProviderSettingsService $providers,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $tenant = current_tenant();

        abort_unless($tenant, 404);
        $providers->assertBelongsToTenant($providerAccount, $tenant);

        $auditLog->deleted($providerAccount, [
            'provider' => $providerAccount->provider,
            'account_reference' => $providerAccount->account_reference,
        ]);

        $providers->delete($providerAccount);

        return redirect()
            ->route('tenant.settings.payment-providers.index')
            ->with('status', 'Payment gateway removed.');
    }

    public function toggle(
        ProviderAccountSetting $providerAccount,
        PaymentProviderSettingsService $providers,
    ): RedirectResponse {
        $tenant = current_tenant();

        abort_unless($tenant, 404);
        $providers->assertBelongsToTenant($providerAccount, $tenant);

        $setting = $providers->toggleActive($providerAccount);

        return redirect()
            ->route('tenant.settings.payment-providers.index')
            ->with('status', $setting->is_active ? 'Gateway enabled.' : 'Gateway disabled.');
    }

    private function validated(Request $request, ?ProviderAccountSetting $existing = null): array
    {
        $provider = $request->input('provider', $existing?->provider);

        $rules = [
            'provider' => ['required', 'in:'.implode(',', array_keys(PaymentProviderSettingsService::PROVIDERS))],
            'account_reference' => ['nullable', 'string', 'max:255'],
            'terminal_id' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'in:test,live'],
            'is_active' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'product' => ['nullable', 'string', 'max:100'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');
        $data['settings'] = ['mode' => $data['mode'] ?? ($existing?->settings['mode'] ?? 'test')];
        unset($data['mode']);

        if (in_array($provider, ['teya', 'worldpay'], true) && ! $existing) {
            $request->validate([
                'api_key' => ['required', 'string', 'max:500'],
                'base_url' => ['required', 'url', 'max:255'],
            ]);
        }

        return $data;
    }
}
