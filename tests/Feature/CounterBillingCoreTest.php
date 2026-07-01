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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterBillingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_bill_combines_unpaid_tickets_for_same_table(): void
    {
        [$tenant, $counter, $waiter, $item] = $this->counterFixture();

        $first = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'quantity' => 1,
            'created_at' => '2026-07-01 10:00:00',
        ]);
        $addon = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'quantity' => 2,
            'is_addon' => true,
            'created_at' => '2026-07-01 10:10:00',
        ]);
        $paid = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'quantity' => 1,
            'created_at' => '2026-07-01 10:20:00',
        ]);
        $paid->forceFill(['payment_status' => Order::PAYMENT_PAID])->save();

        $this->actingAs($counter)
            ->getJson("/counter/bills/{$first->id}")
            ->assertOk()
            ->assertJsonPath('bill.order_ids', [$first->id, $addon->id])
            ->assertJsonPath('bill.subtotal', 36)
            ->assertJsonMissing(['selected_order_id' => $paid->id]);
    }

    public function test_add_on_order_appears_chronologically(): void
    {
        [$tenant, $counter, $waiter, $item] = $this->counterFixture();

        $first = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'created_at' => '2026-07-01 10:00:00',
        ]);
        $second = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 2,
            'created_at' => '2026-07-01 10:05:00',
        ]);
        $addon = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'is_addon' => true,
            'created_at' => '2026-07-01 10:10:00',
        ]);

        $this->actingAs($counter)
            ->getJson('/counter/data')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $first->id)
            ->assertJsonPath('orders.1.id', $second->id)
            ->assertJsonPath('orders.2.id', $addon->id)
            ->assertJsonPath('orders.2.is_addon', true);
    }

    public function test_totals_calculate_from_settings(): void
    {
        [$tenant, $counter, $waiter, $item] = $this->counterFixture([
            'service_charge_percent' => 10,
            'tax_vat_percent' => 20,
        ]);

        $order = $this->createOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'quantity' => 2,
            'created_at' => '2026-07-01 10:00:00',
        ]);

        $this->actingAs($counter)
            ->getJson("/counter/bills/{$order->id}")
            ->assertOk()
            ->assertJsonPath('bill.subtotal', 24)
            ->assertJsonPath('bill.service_charge_total', 2.4)
            ->assertJsonPath('bill.tax_vat_total', 4.8)
            ->assertJsonPath('bill.total_payable', 31.2);
    }

    public function test_counter_billing_is_tenant_isolated(): void
    {
        [$tenantA, $counterA, $waiterA, $itemA] = $this->counterFixture();
        [$tenantB, , $waiterB, $itemB] = $this->counterFixture();

        $visible = $this->createOrder($tenantA, $waiterA, $itemA, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'created_at' => '2026-07-01 10:00:00',
        ]);
        $hidden = $this->createOrder($tenantB, $waiterB, $itemB, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'created_at' => '2026-07-01 10:01:00',
        ]);

        $this->actingAs($counterA)
            ->getJson('/counter/data')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $visible->id)
            ->assertJsonMissing(['id' => $hidden->id]);

        $this->actingAs($counterA)
            ->getJson("/counter/bills/{$hidden->id}")
            ->assertNotFound();
    }

    private function counterFixture(array $settings = []): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Counter Tenant '.uniqid(),
            'slug' => 'counter-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, array_merge([
            'currency_symbol' => '£',
            'service_charge_percent' => 0,
            'tax_vat_percent' => 0,
            'table_count' => 2,
        ], $settings));

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
            'name' => 'Fish Curry',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $counter, $waiter, $item];
    }

    private function createOrder(Tenant $tenant, User $waiter, MenuItem $item, array $overrides = []): Order
    {
        $createdAt = Carbon::parse($overrides['created_at'] ?? now());

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => $overrides['source_type'] ?? OrderSourceTypes::TABLE,
            'table_number' => $overrides['table_number'] ?? null,
            'is_addon' => $overrides['is_addon'] ?? false,
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'quantity' => $overrides['quantity'] ?? 1,
                ],
            ],
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $order->kitchenTicket->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order->fresh(['items', 'kitchenTicket']);
    }
}
