<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Services\AuditLogService;
use App\Services\FeatureGate;
use App\Services\Saas\StripeSaasBillingService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaasVendorSubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.stripe.secret', 'sk_test_subscription');
        Config::set('services.stripe.webhook_secret', 'whsec_subscription');
        Config::set('services.stripe.base_url', 'https://stripe.test');
        Config::set('services.stripe.subscription_grace_days', 7);
    }

    public function test_subscription_created(): void
    {
        [$tenant, $admin, $starter] = $this->subscriptionFixture();

        Http::fake([
            'https://stripe.test/v1/customers' => Http::response([
                'id' => 'cus_poslab_1',
            ], 200),
            'https://stripe.test/v1/subscriptions' => Http::response([
                'id' => 'sub_poslab_1',
                'customer' => 'cus_poslab_1',
                'status' => 'trialing',
                'trial_end' => now()->addDays(14)->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => [
                    'data' => [
                        ['id' => 'si_poslab_1', 'price' => ['id' => 'price_starter_monthly']],
                    ],
                ],
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $starter->id,
                    'billing_interval' => 'monthly',
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post('/tenant/billing/subscribe', [
                'plan_id' => $starter->id,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
            ])
            ->assertRedirect();

        $tenant->refresh();

        $this->assertSame('cus_poslab_1', $tenant->stripe_customer_id);
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertSame($starter->id, $tenant->plan_id);
        $this->assertDatabaseHas('vendor_subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'provider' => 'stripe',
            'stripe_subscription_id' => 'sub_poslab_1',
            'stripe_subscription_item_id' => 'si_poslab_1',
            'stripe_price_id' => 'price_starter_monthly',
            'status' => 'trialing',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::SUBSCRIPTION_CREATED,
            'auditable_id' => $tenant->id,
        ]);
    }

    public function test_webhook_updates_status(): void
    {
        [$tenant, , $starter] = $this->subscriptionFixture();
        $tenant->forceFill(['stripe_customer_id' => 'cus_poslab_2'])->save();
        VendorSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'stripe_customer_id' => 'cus_poslab_2',
            'stripe_subscription_id' => 'sub_poslab_2',
            'stripe_subscription_item_id' => 'si_poslab_2',
            'stripe_price_id' => 'price_starter_monthly',
            'status' => 'trialing',
        ]);

        $payload = [
            'id' => 'evt_sub_updated',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_poslab_2',
                    'customer' => 'cus_poslab_2',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => [
                        'data' => [
                            ['id' => 'si_poslab_2', 'price' => ['id' => 'price_starter_monthly']],
                        ],
                    ],
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $starter->id,
                        'billing_interval' => 'monthly',
                    ],
                ],
            ],
        ];

        $this->postSignedStripeWebhook($payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        $tenant->refresh();

        $this->assertSame('active', $tenant->subscription_status);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe_billing',
            'event_id' => 'evt_sub_updated',
            'event_type' => 'customer.subscription.updated',
            'signature_verified' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::SUBSCRIPTION_WEBHOOK_SYNCED,
            'auditable_id' => $tenant->id,
        ]);
    }

    public function test_expired_tenant_gets_restricted(): void
    {
        [$tenant] = $this->subscriptionFixture();
        $tenant->forceFill([
            'subscription_status' => 'past_due',
            'subscription_ends_at' => now()->subMinute(),
        ])->save();
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $this->actingAs($waiter)
            ->get('/waiter/pos')
            ->assertStatus(402);
    }

    public function test_plan_feature_limits_update_after_plan_change(): void
    {
        [$tenant, $admin, $starter, $pro] = $this->subscriptionFixture();
        $tenant->forceFill([
            'plan_id' => $starter->id,
            'stripe_customer_id' => 'cus_poslab_3',
            'subscription_status' => 'active',
        ])->save();
        VendorSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'stripe_customer_id' => 'cus_poslab_3',
            'stripe_subscription_id' => 'sub_poslab_3',
            'stripe_subscription_item_id' => 'si_poslab_3',
            'stripe_price_id' => 'price_starter_monthly',
            'status' => 'active',
        ]);

        $this->assertSame(5, app(FeatureGate::class)->limitFor($tenant->fresh(), 'locations'));

        Http::fake([
            'https://stripe.test/v1/subscriptions/sub_poslab_3' => Http::response([
                'id' => 'sub_poslab_3',
                'customer' => 'cus_poslab_3',
                'status' => 'active',
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => [
                    'data' => [
                        ['id' => 'si_poslab_3', 'price' => ['id' => 'price_pro_monthly']],
                    ],
                ],
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $pro->id,
                    'billing_interval' => 'monthly',
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->patch('/tenant/billing/plan', [
                'plan_id' => $pro->id,
                'billing_interval' => 'monthly',
            ])
            ->assertRedirect();

        $tenant->refresh();

        $this->assertSame($pro->id, $tenant->plan_id);
        $this->assertSame(50, app(FeatureGate::class)->limitFor($tenant, 'locations'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::SUBSCRIPTION_CHANGED,
            'auditable_id' => $tenant->id,
        ]);
    }

    private function subscriptionFixture(): array
    {
        $starter = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'stripe_monthly_price_id' => 'price_starter_monthly',
            'stripe_yearly_price_id' => 'price_starter_yearly',
            'is_active' => true,
        ]);
        $pro = Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'stripe_monthly_price_id' => 'price_pro_monthly',
            'stripe_yearly_price_id' => 'price_pro_yearly',
            'is_active' => true,
        ]);
        PlanFeature::query()->create([
            'plan_id' => $starter->id,
            'feature_key' => 'locations',
            'enabled' => true,
            'limit' => 5,
        ]);
        PlanFeature::query()->create([
            'plan_id' => $pro->id,
            'feature_key' => 'locations',
            'enabled' => true,
            'limit' => 50,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Billing Tenant '.uniqid(),
            'slug' => 'billing-tenant-'.uniqid(),
            'plan_id' => $starter->id,
            'subscription_status' => 'active',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);

        return [$tenant, $admin, $starter, $pro];
    }

    private function postSignedStripeWebhook(array $payload)
    {
        $content = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$content, 'whsec_subscription');

        return $this->call('POST', '/api/webhooks/stripe/subscriptions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $content);
    }
}
