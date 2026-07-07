<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_payment_gateways(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Pay Tenant', 'slug' => 'pay-tenant']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::ADMIN]);

        $this->actingAs($admin)
            ->get('/tenant/settings/payment-providers')
            ->assertOk()
            ->assertSee('Payment gateways');

        $this->actingAs($admin)
            ->post('/tenant/settings/payment-providers', [
                'provider' => 'external_card',
                'account_reference' => 'counter-terminal-1',
                'terminal_id' => 'TERM-1',
                'mode' => 'test',
                'is_active' => 1,
            ])
            ->assertRedirect('/tenant/settings/payment-providers');

        $this->assertDatabaseHas('provider_account_settings', [
            'tenant_id' => $tenant->id,
            'provider' => 'external_card',
            'account_reference' => 'counter-terminal-1',
            'is_active' => true,
        ]);

        $setting = $tenant->providerAccountSettings()->firstOrFail();

        $this->actingAs($admin)
            ->patch("/tenant/settings/payment-providers/{$setting->id}/toggle")
            ->assertRedirect('/tenant/settings/payment-providers');

        $this->assertFalse($setting->fresh()->is_active);
    }
}
