<?php

namespace Tests\Feature;

use App\Exceptions\PaymentProviderException;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Payments\Adapters\WorldpayAdapter;
use App\Payments\DTO\PaymentProviderRequest;
use App\Services\Billing\BillingService;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorldpayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mocked_success_payment(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(18);
        $this->configureWorldpay($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://worldpay.test/terminal/payments' => Http::response([
                'id' => 'wp_txn_1',
                'status' => 'authorized',
                'message' => 'Authorized',
            ], 200),
        ]);

        $result = app(WorldpayAdapter::class)->charge(new PaymentProviderRequest(
            tenant: $tenant,
            branchId: null,
            order: $order,
            cashier: $cashier,
            amount: 18,
            terminalId: null,
            metadata: ['currency' => 'GBP']
        ));

        $this->assertSame('worldpay', $result->provider);
        $this->assertSame(Order::PAYMENT_PAID, $result->status);
        $this->assertSame('wp_txn_1', $result->providerTransactionId);
        $this->assertSame('WP-TERM-1', $result->terminalId);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization')
            && $request['flow'] === 'terminal'
            && $request['terminal_id'] === 'WP-TERM-1'
            && $request['amount']['value'] === 1800);

        $this->assertDatabaseHas('payment_provider_logs', [
            'tenant_id' => $tenant->id,
            'provider' => 'worldpay',
            'action' => 'create_authorization',
            'status_code' => 200,
            'success' => true,
        ]);
    }

    public function test_mocked_failed_payment(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(18);
        $this->configureWorldpay($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://worldpay.test/terminal/payments' => Http::response([
                'message' => 'Worldpay refused authorization',
            ], 402),
        ]);

        $this->expectException(PaymentProviderException::class);
        $this->expectExceptionMessage('Worldpay refused authorization');

        app(WorldpayAdapter::class)->charge(new PaymentProviderRequest(
            tenant: $tenant,
            branchId: null,
            order: $order,
            cashier: $cashier,
            amount: 18,
            terminalId: 'WP-TERM-1',
            metadata: ['currency' => 'GBP']
        ));
    }

    public function test_webhook_event_stored(): void
    {
        [$tenant] = $this->paymentFixture();
        $this->configureWorldpay($tenant);
        $payload = [
            'id' => 'evt_wp_1',
            'type' => 'payment.authorized',
            'tenant_id' => $tenant->id,
            'account_reference' => 'worldpay-demo-account',
            'card_number' => '4111111111111111',
        ];
        $content = json_encode($payload);
        $signature = hash_hmac('sha256', $content, 'worldpay-webhook-secret');

        $this->call('POST', '/api/webhooks/worldpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_EVENT_SIGNATURE' => $signature,
        ], $content)->assertOk()->assertJson(['received' => true]);

        $event = DB::table('webhook_events')->where('event_id', 'evt_wp_1')->first();

        $this->assertNotNull($event);
        $this->assertSame($tenant->id, (int) $event->tenant_id);
        $this->assertSame('worldpay', $event->provider);
        $this->assertSame('payment.authorized', $event->event_type);
        $this->assertSame(1, (int) $event->signature_verified);
        $this->assertStringNotContainsString('4111111111111111', $event->payload);
    }

    public function test_payment_record_created_safely(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(24);
        $setting = $this->configureWorldpay($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://worldpay.test/terminal/payments' => Http::response([
                'id' => 'wp_txn_safe',
                'status' => 'authorized',
                'pan' => '4111111111111111',
                'cvv' => '123',
            ], 200),
        ]);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 0, 24, [
            'provider' => 'worldpay',
            'terminal_id' => 'WP-TERM-1',
            'metadata' => [
                'currency' => 'GBP',
                'card_number' => '4111111111111111',
                'cvv' => '123',
            ],
        ]);

        $this->assertSame('worldpay', $payment->provider);
        $this->assertSame('wp_txn_safe', $payment->provider_transaction_id);
        $this->assertSame(Order::PAYMENT_PAID, $payment->status);
        $this->assertArrayNotHasKey('card_number', $payment->provider_metadata ?? []);
        $this->assertArrayNotHasKey('cvv', $payment->provider_metadata ?? []);

        $rawCredentials = DB::table('provider_account_settings')
            ->where('id', $setting->id)
            ->value('encrypted_credentials');
        $this->assertStringNotContainsString('secret-worldpay-key', $rawCredentials);

        $log = DB::table('payment_provider_logs')->where('provider', 'worldpay')->first();
        $this->assertStringNotContainsString('4111111111111111', json_encode($log->response_payload));
        $this->assertStringNotContainsString('123', json_encode($log->response_payload));
    }

    private function configureWorldpay(Tenant $tenant): ProviderAccountSetting
    {
        return ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'worldpay',
            'account_reference' => 'worldpay-demo-account',
            'terminal_id' => 'WP-TERM-1',
            'settings' => ['mode' => 'test'],
            'encrypted_credentials' => [
                'api_key' => 'secret-worldpay-key',
                'base_url' => 'https://worldpay.test',
                'product' => 'terminal',
                'webhook_secret' => 'worldpay-webhook-secret',
                'timeout' => 5,
            ],
            'is_active' => true,
        ]);
    }

    private function createTakeawayOrder(Tenant $tenant, User $waiter, MenuItem $item): Order
    {
        return app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);
    }

    private function paymentFixture(float $price = 25): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Worldpay Tenant '.uniqid(),
            'slug' => 'worldpay-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'currency_symbol' => 'GBP',
            'currency_code' => 'GBP',
            'service_charge_percent' => 0,
            'tax_vat_percent' => 0,
            'table_count' => 1,
        ]);

        $cashier = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
        ]);
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Worldpay Items',
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Worldpay Item',
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $cashier, $waiter, $item];
    }
}
