<?php

namespace App\Services\Settings;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tables\TableSyncService;

class RestaurantSettingsService
{
    public const KEY = 'restaurant.settings';

    public function defaults(Tenant $tenant): array
    {
        return [
            'restaurant_name' => $tenant->name,
            'logo_text' => $tenant->name,
            'logo_upload_path' => null,
            'address' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'currency_symbol' => '£',
            'currency_code' => 'GBP',
            'service_charge_percent' => 0,
            'service_charge_enabled' => true,
            'tax_vat_percent' => 0,
            'tax_enabled' => true,
            'table_count' => 0,
            'invoice_footer' => null,
            'receipt_header' => null,
            'timezone' => config('app.timezone', 'UTC'),
            'business_registration_number' => null,
            'default_card_provider' => 'external_card',
            'theme_color' => '#0f172a',
        ];
    }

    public function get(Tenant $tenant): array
    {
        $setting = $this->setting($tenant);

        return array_merge($this->defaults($tenant), $setting?->value ?? []);
    }

    public function save(Tenant $tenant, array $values): TenantSetting
    {
        $current = $this->get($tenant);
        $payload = array_merge($current, $values);

        $setting = TenantSetting::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'branch_id' => null,
                'key' => self::KEY,
            ],
            [
                'value' => $payload,
            ]
        );

        if (array_key_exists('table_count', $values)) {
            app(TableSyncService::class)->syncTenantTables($tenant, (int) $payload['table_count']);
        }

        return $setting;
    }

    public function setting(Tenant $tenant): ?TenantSetting
    {
        return TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('branch_id')
            ->where('key', self::KEY)
            ->first();
    }
}
