<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Backups\BackupService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('blocked@example.test|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'blocked@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'blocked@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_menu_item_category_must_belong_to_current_tenant(): void
    {
        [$tenantA, $adminA] = $this->tenantUser(Roles::ADMIN);
        [$tenantB] = $this->tenantUser(Roles::ADMIN);

        $foreignCategory = Category::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Foreign Category',
        ]);

        $this->actingAs($adminA)
            ->post('/tenant/menu/items', [
                'category_id' => $foreignCategory->id,
                'name' => 'Cross Tenant Item',
                'description' => 'Should not be accepted.',
                'price' => '10.00',
                'is_available' => '1',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('menu_items', [
            'tenant_id' => $tenantA->id,
            'name' => 'Cross Tenant Item',
        ]);
    }

    public function test_worldpay_webhook_rejects_invalid_signature(): void
    {
        [$tenant] = $this->tenantUser(Roles::ADMIN);

        ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'worldpay',
            'account_reference' => 'worldpay-security-account',
            'terminal_id' => 'TERM-SEC',
            'encrypted_credentials' => [
                'webhook_secret' => 'expected-secret',
            ],
            'is_active' => true,
        ]);

        $payload = [
            'id' => 'evt_invalid_worldpay',
            'type' => 'payment.authorized',
            'tenant_id' => $tenant->id,
            'account_reference' => 'worldpay-security-account',
        ];

        $this->call('POST', '/api/webhooks/worldpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_EVENT_SIGNATURE' => 'bad-signature',
        ], json_encode($payload))->assertStatus(400);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt_invalid_worldpay',
            'provider' => 'worldpay',
            'signature_verified' => false,
        ]);
    }

    public function test_backup_download_requires_authorized_tenant_and_existing_private_file(): void
    {
        Storage::fake('backups');
        [$tenantA, $adminA] = $this->tenantWithBackups('Tenant A');
        [, $adminB] = $this->tenantWithBackups('Tenant B');

        $backup = app(BackupService::class)->createTenantBackup($tenantA, Backup::TYPE_DATABASE, $adminA);

        $this->actingAs($adminB)
            ->get("/tenant/backups/{$backup->id}/download")
            ->assertNotFound();

        Storage::disk('backups')->delete($backup->path);

        $this->actingAs($adminA)
            ->get("/tenant/backups/{$backup->id}/download")
            ->assertNotFound();
    }

    public function test_web_pages_send_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy');
    }

    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Security Tenant '.uniqid(),
            'slug' => 'security-tenant-'.uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);

        return [$tenant, $user];
    }

    private function tenantWithBackups(string $name): array
    {
        $plan = Plan::query()->create([
            'name' => $name.' Plan',
            'slug' => 'security-backup-plan-'.uniqid(),
            'is_active' => true,
        ]);

        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'feature_key' => BackupService::featureKey(),
            'enabled' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => $name.' '.uniqid(),
            'slug' => 'security-backup-tenant-'.uniqid(),
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);

        return [$tenant, $admin];
    }
}
