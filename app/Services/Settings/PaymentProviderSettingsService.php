<?php

namespace App\Services\Settings;

use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentProviderSettingsService
{
    public const PROVIDERS = [
        'cash' => 'Cash (manual)',
        'external_card' => 'External card terminal',
        'teya' => 'Teya',
        'worldpay' => 'Worldpay',
    ];

    public function listForTenant(Tenant $tenant): Collection
    {
        return ProviderAccountSetting::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('provider')
            ->orderBy('account_reference')
            ->get();
    }

    public function providerLabel(string $provider): string
    {
        return self::PROVIDERS[$provider] ?? ucfirst(str_replace('_', ' ', $provider));
    }

    public function credentialFields(string $provider): array
    {
        return match ($provider) {
            'teya' => ['api_key', 'base_url', 'timeout'],
            'worldpay' => ['api_key', 'base_url', 'product', 'webhook_secret', 'timeout'],
            default => [],
        };
    }

    public function create(Tenant $tenant, array $data): ProviderAccountSetting
    {
        $this->assertUniqueProvider($tenant, $data['provider'], $data['account_reference'] ?? null);

        return ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'provider' => $data['provider'],
            'account_reference' => $data['account_reference'] ?? null,
            'terminal_id' => $data['terminal_id'] ?? null,
            'settings' => $data['settings'] ?? ['mode' => 'test'],
            'encrypted_credentials' => $this->credentialsPayload($data),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function update(ProviderAccountSetting $setting, array $data): ProviderAccountSetting
    {
        if (
            isset($data['provider'], $data['account_reference'])
            && (
                $setting->provider !== $data['provider']
                || $setting->account_reference !== $data['account_reference']
            )
        ) {
            $this->assertUniqueProvider(
                $setting->tenant,
                $data['provider'],
                $data['account_reference'],
                $setting->id,
            );
        }

        $credentials = $this->mergeCredentials($setting, $data);

        $setting->update([
            'provider' => $data['provider'] ?? $setting->provider,
            'account_reference' => $data['account_reference'] ?? $setting->account_reference,
            'terminal_id' => $data['terminal_id'] ?? $setting->terminal_id,
            'settings' => array_merge($setting->settings ?? [], $data['settings'] ?? []),
            'encrypted_credentials' => $credentials,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $setting->is_active,
        ]);

        return $setting->fresh();
    }

    public function toggleActive(ProviderAccountSetting $setting): ProviderAccountSetting
    {
        $setting->update(['is_active' => ! $setting->is_active]);

        return $setting->fresh();
    }

    public function delete(ProviderAccountSetting $setting): void
    {
        $setting->delete();
    }

    public function assertBelongsToTenant(ProviderAccountSetting $setting, Tenant $tenant): void
    {
        abort_unless((int) $setting->tenant_id === (int) $tenant->id, 404);
    }

    private function assertUniqueProvider(
        Tenant $tenant,
        string $provider,
        ?string $accountReference,
        ?int $ignoreId = null,
    ): void {
        $query = ProviderAccountSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', $provider)
            ->where('account_reference', $accountReference);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'account_reference' => 'This provider account is already configured for this tenant.',
            ]);
        }
    }

    private function credentialsPayload(array $data): ?array
    {
        $provider = $data['provider'] ?? null;

        if (! $provider || ! in_array($provider, ['teya', 'worldpay'], true)) {
            return null;
        }

        $credentials = [];

        foreach ($this->credentialFields($provider) as $field) {
            $value = $data[$field] ?? null;

            if ($value !== null && $value !== '') {
                $credentials[$field] = $field === 'timeout' ? (int) $value : $value;
            }
        }

        return $credentials ?: null;
    }

    private function mergeCredentials(ProviderAccountSetting $setting, array $data): ?array
    {
        $provider = $data['provider'] ?? $setting->provider;
        $existing = $setting->encrypted_credentials ?? [];

        if (! in_array($provider, ['teya', 'worldpay'], true)) {
            return null;
        }

        $merged = $existing;

        foreach ($this->credentialFields($provider) as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === null || $value === '') {
                continue;
            }

            $merged[$field] = $field === 'timeout' ? (int) $value : $value;
        }

        return $merged ?: null;
    }
}
