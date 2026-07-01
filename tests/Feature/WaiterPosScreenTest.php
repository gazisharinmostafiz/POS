<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiterPosScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_creates_table_order(): void
    {
        [$tenant, $waiter, $item] = $this->waiterFixture();

        $response = $this->actingAs($waiter)->postJson('/waiter/pos/orders', $this->orderPayload([
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('order.source_type', OrderSourceTypes::TABLE)
            ->assertJsonPath('order.table_number', 1);

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'waiter_id' => $waiter->id,
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'subtotal' => 24,
        ]);
        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 1,
            'status' => RestaurantTable::STATUS_ORDER_SENT,
        ]);
        $this->assertSame(1, KitchenTicket::query()->count());
    }

    public function test_waiter_creates_takeaway_order(): void
    {
        [, $waiter, $item] = $this->waiterFixture();

        $response = $this->actingAs($waiter)->postJson('/waiter/pos/orders', $this->orderPayload([
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('order.source_type', OrderSourceTypes::TAKEAWAY)
            ->assertJsonPath('order.table_number', null);

        $this->assertDatabaseHas('orders', [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'table_number' => null,
            'subtotal' => 12,
        ]);
    }

    public function test_waiter_creates_walk_in_order(): void
    {
        [, $waiter, $item] = $this->waiterFixture();

        $response = $this->actingAs($waiter)->postJson('/waiter/pos/orders', $this->orderPayload([
            'source_type' => OrderSourceTypes::WALK_IN,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('order.source_type', OrderSourceTypes::WALK_IN)
            ->assertJsonPath('order.table_number', null);

        $this->assertDatabaseHas('orders', [
            'source_type' => OrderSourceTypes::WALK_IN,
            'table_number' => null,
            'subtotal' => 12,
        ]);
    }

    public function test_waiter_adds_item_by_card_click(): void
    {
        [, $waiter] = $this->waiterFixture();

        $this->actingAs($waiter)
            ->get('/waiter/pos')
            ->assertOk()
            ->assertSee('data-menu-card', false)
            ->assertSee('@click="addToCart(item)"', false);
    }

    public function test_add_on_order_is_created_separately(): void
    {
        [, $waiter, $item] = $this->waiterFixture();

        $baseResponse = $this->actingAs($waiter)->postJson('/waiter/pos/orders', $this->orderPayload([
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]));
        $baseResponse->assertCreated();
        $baseOrderId = $baseResponse->json('order.id');

        $this->actingAs($waiter)
            ->getJson('/waiter/pos/tables/1/active-order')
            ->assertOk()
            ->assertJsonPath('order.id', $baseOrderId);

        $addonResponse = $this->actingAs($waiter)->postJson('/waiter/pos/orders/addon', $this->orderPayload([
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]));

        $addonResponse->assertCreated()
            ->assertJsonPath('order.is_addon', true);

        $addonOrderId = $addonResponse->json('order.id');

        $this->assertNotSame($baseOrderId, $addonOrderId);
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(2, KitchenTicket::query()->count());
        $this->assertTrue(KitchenTicket::query()->where('order_id', $addonOrderId)->firstOrFail()->is_addon);
    }

    private function waiterFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Waiter POS Tenant',
            'slug' => 'waiter-pos-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'currency_symbol' => '£',
            'table_count' => 2,
        ]);

        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Fish Curry',
            'description' => 'House curry',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$tenant, $waiter, $item];
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'kitchen_note' => 'No onions',
            'items' => [],
        ], $overrides);
    }
}
