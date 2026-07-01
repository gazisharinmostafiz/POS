<?php

namespace Tests\Feature;

use App\Exceptions\PaymentProviderException;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Payments\Adapters\TeyaAdapter;
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

class TeyaPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_can_create_mocked_payment(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(12);
        $this->configureTeya($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://teya.test/payments' => Http::response([
                'id' => 'teya_txn_1',
                'status' => 'approved',
                'message' => 'Approved',
            ], 200),
        ]);

        $result = app(TeyaAdapter::class)->charge(new PaymentProviderRequest(
            tenant: $tenant,
            branchId: null,
            order: $order,
            cashier: $cashier,
            amount: 12,
            terminalId: null,
            metadata: ['currency' => 'GBP']
        ));

        $this->assertSame('teya', $result->provider);
        $this->assertSame(Order::PAYMENT_PAID, $result->status);
        $this->assertSame('teya_txn_1', $result->providerTransactionId);
        $this->assertSame('TEYA-TERM-1', $result->terminalId);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization')
            && $request['terminal_id'] === 'TEYA-TERM-1'
            && $request['amount']['value'] === 1200
            && $request['amount']['currency'] === 'GBP');

        $this->assertDatabaseHas('payment_provider_logs', [
            'tenant_id' => $tenant->id,
            'provider' => 'teya',
            'action' => 'create_payment',
            'status_code' => 200,
            'success' => true,
        ]);

        $log = DB::table('payment_provider_logs')->where('provider', 'teya')->first();
        $this->assertStringNotContainsString('secret-teya-key', json_encode($log->request_payload));
        $this->assertStringNotContainsString('secret-teya-key', json_encode($log->response_payload));
    }

    public function test_failed_payment_returns_clear_error(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(12);
        $this->configureTeya($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://teya.test/payments' => Http::response([
                'message' => 'Terminal declined payment',
            ], 402),
        ]);

        $this->expectException(PaymentProviderException::class);
        $this->expectExceptionMessage('Terminal declined payment');

        app(TeyaAdapter::class)->charge(new PaymentProviderRequest(
            tenant: $tenant,
            branchId: null,
            order: $order,
            cashier: $cashier,
            amount: 12,
            terminalId: 'TEYA-TERM-1',
            metadata: ['currency' => 'GBP']
        ));
    }

    public function test_successful_response_creates_payment_record(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(35);
        $this->configureTeya($tenant);
        $order = $this->createTakeawayOrder($tenant, $waiter, $item);

        Http::fake([
            'https://teya.test/payments' => Http::response([
                'id' => 'teya_txn_35',
                'status' => 'approved',
            ], 200),
        ]);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 0, 35, [
            'provider' => 'teya',
            'terminal_id' => 'TEYA-TERM-1',
            'metadata' => ['currency' => 'GBP'],
        ]);

        $this->assertSame('teya', $payment->provider);
        $this->assertSame('teya_txn_35', $payment->provider_transaction_id);
        $this->assertSame('TEYA-TERM-1', $payment->terminal_id);
        $this->assertSame(Order::PAYMENT_PAID, $payment->status);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'provider' => 'teya',
            'provider_transaction_id' => 'teya_txn_35',
            'terminal_id' => 'TEYA-TERM-1',
            'status' => Order::PAYMENT_PAID,
        ]);
    }

    public function test_credentials_are_encrypted_and_not_exposed_to_frontend(): void
    {
        [$tenant, $cashier] = $this->paymentFixture();
        $setting = $this->configureTeya($tenant);

        $rawCredentials = DB::table('provider_account_settings')
            ->where('id', $setting->id)
            ->value('encrypted_credentials');

        $this->assertNotNull($rawCredentials);
        $this->assertStringNotContainsString('secret-teya-key', $rawCredentials);

        $this->actingAs($cashier)
            ->getJson('/counter/data')
            ->assertOk()
            ->assertJsonMissing(['api_key' => 'secret-teya-key'])
            ->assertDontSee('secret-teya-key');
    }

    private function configureTeya(Tenant $tenant): ProviderAccountSetting
    {
        return ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'teya',
            'account_reference' => 'teya-demo-account',
            'terminal_id' => 'TEYA-TERM-1',
            'settings' => ['mode' => 'test'],
            'encrypted_credentials' => [
                'api_key' => 'secret-teya-key',
                'base_url' => 'https://teya.test',
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
            'name' => 'Teya Tenant '.uniqid(),
            'slug' => 'teya-tenant-'.uniqid(),
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
            'name' => 'Teya Items',
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Teya Item',
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $cashier, $waiter, $item];
    }
}
