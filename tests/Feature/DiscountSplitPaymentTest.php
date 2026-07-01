<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountSplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifty_five_paid_as_twenty_cash_and_thirty_five_card_is_paid(): void
    {
        [, $counter, $order] = $this->billingFixture(55);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$order->id}/payment", [
                'cash_amount' => 20,
                'card_amount' => 35,
            ])
            ->assertCreated()
            ->assertJsonPath('payment.cash_amount', 20)
            ->assertJsonPath('payment.card_amount', 35)
            ->assertJsonPath('payment.total_paid', 55)
            ->assertJsonPath('payment.balance', 0)
            ->assertJsonPath('payment.change', 0)
            ->assertJsonPath('payment.status', Order::PAYMENT_PAID);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'cash_amount' => 20,
            'card_amount' => 35,
            'status' => Order::PAYMENT_PAID,
        ]);
    }

    public function test_fifty_five_paid_as_sixty_cash_returns_five_change(): void
    {
        [, $counter, $order] = $this->billingFixture(55);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$order->id}/payment", [
                'cash_amount' => 60,
                'card_amount' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('payment.total_paid', 60)
            ->assertJsonPath('payment.balance', 0)
            ->assertJsonPath('payment.change', 5)
            ->assertJsonPath('payment.status', Order::PAYMENT_PAID);
    }

    public function test_fifty_five_paid_as_twenty_cash_and_twenty_card_is_partial(): void
    {
        [, $counter, $order] = $this->billingFixture(55);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$order->id}/payment", [
                'cash_amount' => 20,
                'card_amount' => 20,
            ])
            ->assertCreated()
            ->assertJsonPath('payment.total_paid', 40)
            ->assertJsonPath('payment.balance', 15)
            ->assertJsonPath('payment.change', 0)
            ->assertJsonPath('payment.status', Order::PAYMENT_PARTIAL);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_PARTIAL,
        ]);
    }

    public function test_split_sixty_by_three_is_twenty_each(): void
    {
        [, $counter, $order] = $this->billingFixture(60);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$order->id}/split", [
                'people_count' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('split_bill.people_count', 3)
            ->assertJsonPath('split_bill.total_payable', 60)
            ->assertJsonPath('split_bill.amount_per_person', 20)
            ->assertJsonPath('split_bill.rounding_difference', 0);

        $this->assertDatabaseHas('split_bills', [
            'order_id' => $order->id,
            'people_count' => 3,
            'amount_per_person' => 20,
        ]);
    }

    public function test_discount_flat_and_percent_work(): void
    {
        [, $counter, $flatOrder] = $this->billingFixture(55);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$flatOrder->id}/discount", [
                'type' => 'flat',
                'value' => 5,
                'reason' => 'Manager comp',
            ])
            ->assertOk()
            ->assertJsonPath('order.discount_total', 5)
            ->assertJsonPath('bill.subtotal', 55)
            ->assertJsonPath('bill.discount_total', 5)
            ->assertJsonPath('bill.total_payable', 50);

        $this->assertDatabaseHas('orders', [
            'id' => $flatOrder->id,
            'discount_type' => 'flat',
            'discount_value' => 5,
            'discount_total' => 5,
            'discount_reason' => 'Manager comp',
            'discount_applied_by' => $counter->id,
        ]);

        [, $secondCounter, $percentOrder] = $this->billingFixture(60);

        $this->actingAs($secondCounter)
            ->postJson("/counter/bills/{$percentOrder->id}/discount", [
                'type' => 'percent',
                'value' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('order.discount_total', 6)
            ->assertJsonPath('bill.subtotal', 60)
            ->assertJsonPath('bill.discount_total', 6)
            ->assertJsonPath('bill.total_payable', 54);
    }

    private function billingFixture(float $price): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Payment Tenant '.uniqid(),
            'slug' => 'payment-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'currency_symbol' => '£',
            'service_charge_percent' => 0,
            'tax_vat_percent' => 0,
            'table_count' => 1,
        ]);

        $counter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
        ]);
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Bill Item',
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        return [$tenant, $counter, $order];
    }
}
