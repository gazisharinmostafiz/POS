<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_restaurant_settings(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->put('/tenant/settings/restaurant', $this->validPayload([
                'restaurant_name' => 'Harbour Kitchen',
                'currency_symbol' => '$',
                'currency_code' => 'USD',
                'table_count' => 24,
            ]))
            ->assertRedirect('/tenant/settings/restaurant');

        $setting = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', RestaurantSettingsService::KEY)
            ->firstOrFail();

        $this->assertSame('Harbour Kitchen', $setting->value['restaurant_name']);
        $this->assertSame('$', $setting->value['currency_symbol']);
        $this->assertSame('USD', $setting->value['currency_code']);
        $this->assertSame(24, $setting->value['table_count']);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'event' => AuditLogService::SETTINGS_CHANGED,
            'auditable_type' => TenantSetting::class,
            'auditable_id' => $setting->id,
        ]);
    }

    public function test_logo_upload_is_validated_and_stored(): void
    {
        Storage::fake('public');

        [$tenant, $admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->put('/tenant/settings/restaurant', array_merge(
                $this->validPayload(),
                ['logo' => UploadedFile::fake()->create('not-a-logo.pdf', 12, 'application/pdf')]
            ))
            ->assertSessionHasErrors('logo');

        $this->actingAs($admin)
            ->put('/tenant/settings/restaurant', array_merge(
                $this->validPayload(),
                ['logo' => $this->fakePngUpload()]
            ))
            ->assertRedirect('/tenant/settings/restaurant');

        $setting = app(RestaurantSettingsService::class)->setting($tenant);

        $this->assertNotNull($setting->value['logo_upload_path']);
        Storage::disk('public')->assertExists($setting->value['logo_upload_path']);
    }

    public function test_currency_displays_from_settings(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();

        app(RestaurantSettingsService::class)->save($tenant, $this->validPayload([
            'restaurant_name' => 'Currency Cafe',
            'currency_symbol' => '€',
            'currency_code' => 'EUR',
        ]));

        $this->actingAs($admin)
            ->get('/tenant/settings/restaurant')
            ->assertOk()
            ->assertSee('€ EUR')
            ->assertSee('Currency Cafe');
    }

    public function test_only_admin_super_admin_or_platform_owner_can_edit_settings(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($admin)
            ->get('/tenant/settings/restaurant')
            ->assertOk();

        $this->actingAs($waiter)
            ->get('/tenant/settings/restaurant')
            ->assertForbidden();

        $this->actingAs($platformOwner)
            ->get("/platform/vendors/{$tenant->id}/settings")
            ->assertOk();
    }

    private function tenantAdmin(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Settings Tenant',
            'slug' => 'settings-tenant',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);

        return [$tenant, $admin];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'restaurant_name' => 'Settings Restaurant',
            'logo_text' => 'SR',
            'address' => '1 Settings Street',
            'phone' => '020 0000 0000',
            'email' => 'settings@example.test',
            'website' => 'https://settings.example.test',
            'currency_symbol' => '£',
            'currency_code' => 'GBP',
            'service_charge_percent' => 12.5,
            'service_charge_enabled' => true,
            'tax_vat_percent' => 20,
            'tax_enabled' => true,
            'table_count' => 12,
            'invoice_footer' => 'Thank you',
            'timezone' => 'Europe/London',
            'default_card_provider' => 'external_card',
            'theme_color' => '#0f766e',
        ], $overrides);
    }

    private function fakePngUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }
}
