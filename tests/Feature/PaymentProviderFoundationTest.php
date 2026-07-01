<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_adapter_completes_payment(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(25);
        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 25, 0);

        $this->assertSame('cash', $payment->provider);
        $this->assertNotNull($payment->provider_transaction_id);
        $this->assertSame('paid', $payment->status);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'provider' => 'cash',
            'cash_amount' => 25,
            'card_amount' => 0,
        ]);
    }

    public function test_external_card_adapter_records_reference(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(35);
        ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'external_card',
            'terminal_id' => 'TERM-1',
            'settings' => ['mode' => 'manual'],
        ]);
        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 0, 35, [
            'provider' => 'external_card',
            'terminal_id' => 'TERM-1',
            'manual_reference' => 'CARD-REF-123',
        ]);

        $this->assertSame('external_card', $payment->provider);
        $this->assertSame('CARD-REF-123', $payment->provider_transaction_id);
        $this->assertSame('TERM-1', $payment->terminal_id);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'provider' => 'external_card',
            'provider_transaction_id' => 'CARD-REF-123',
            'terminal_id' => 'TERM-1',
        ]);
    }

    public function test_webhook_event_can_be_stored(): void
    {
        [$tenant] = $this->paymentFixture();

        $event = app(PaymentService::class)->storeWebhookEvent(
            'external_card',
            ['id' => 'evt_123', 'type' => 'payment.completed', 'amount' => 25],
            ['x-signature' => 'test-signature'],
            $tenant,
            null,
            true
        );

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'tenant_id' => $tenant->id,
            'provider' => 'external_card',
            'event_id' => 'evt_123',
            'event_type' => 'payment.completed',
            'signature_verified' => true,
        ]);
    }

    public function test_sensitive_fields_are_not_stored(): void
    {
        [$tenant, $cashier, $waiter, $item] = $this->paymentFixture(35);
        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 0, 35, [
            'provider' => 'external_card',
            'terminal_id' => 'TERM-2',
            'manual_reference' => 'SAFE-REF',
            'metadata' => [
                'card_number' => '4111111111111111',
                'cvv' => '123',
                'receipt_reference' => 'R-1',
                'nested' => ['pan' => '5555555555554444', 'safe' => 'ok'],
            ],
        ]);

        $metadata = $payment->provider_metadata;
        $this->assertSame('R-1', $metadata['receipt_reference']);
        $this->assertSame('ok', $metadata['nested']['safe']);
        $this->assertArrayNotHasKey('card_number', $metadata);
        $this->assertArrayNotHasKey('cvv', $metadata);
        $this->assertArrayNotHasKey('pan', $metadata['nested']);

        $webhook = app(PaymentService::class)->storeWebhookEvent('external_card', [
            'id' => 'evt_sensitive',
            'card_number' => '4111111111111111',
            'safe' => 'value',
        ]);

        $this->assertArrayNotHasKey('card_number', $webhook->payload);
        $this->assertSame('value', $webhook->payload['safe']);
    }

    private function paymentFixture(float $price = 25): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Provider Tenant '.uniqid(),
            'slug' => 'provider-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, ['table_count' => 1]);

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
            'name' => 'Provider Items',
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Provider Item',
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $cashier, $waiter, $item];
    }
}
