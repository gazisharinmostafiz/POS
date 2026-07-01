<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDomainCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_with_items(): void
    {
        [$tenant, $waiter, $item] = $this->orderFixture();

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 3,
            'kitchen_note' => 'No onions',
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 2, 'item_note' => 'Extra spicy'],
            ],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame(OrderSourceTypes::TABLE, $order->source_type);
        $this->assertSame(3, $order->table_number);
        $this->assertSame('24.00', $order->subtotal);
        $this->assertSame('24.00', $order->total);
        $this->assertCount(1, $order->items);
        $this->assertInstanceOf(KitchenTicket::class, $order->kitchenTicket);
    }

    public function test_order_number_is_unique(): void
    {
        [$tenant, $waiter, $item] = $this->orderFixture();

        $first = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $second = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::WALK_IN,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertDatabaseHas('orders', ['order_number' => $first->order_number]);
        $this->assertDatabaseHas('orders', ['order_number' => $second->order_number]);
    }

    public function test_menu_item_snapshots_are_saved(): void
    {
        [$tenant, $waiter, $item] = $this->orderFixture([
            'name' => 'Original Name',
            'price' => 9.75,
        ]);

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 3]],
        ]);

        $item->update([
            'name' => 'Changed Name',
            'price' => 99.99,
        ]);

        $orderItem = $order->items()->firstOrFail();

        $this->assertSame('Original Name', $orderItem->item_name_snapshot);
        $this->assertSame('9.75', $orderItem->unit_price_snapshot);
        $this->assertSame(3, $orderItem->quantity);
        $this->assertSame('29.25', $orderItem->line_total);
    }

    public function test_add_on_order_creates_separate_kitchen_ticket(): void
    {
        [$tenant, $waiter, $item] = $this->orderFixture();

        $baseOrder = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 4,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $addonOrder = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 4,
            'is_addon' => true,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $this->assertNotSame($baseOrder->id, $addonOrder->id);
        $this->assertFalse($baseOrder->kitchenTicket->is_addon);
        $this->assertTrue($addonOrder->kitchenTicket->is_addon);
        $this->assertNotSame($baseOrder->kitchenTicket->id, $addonOrder->kitchenTicket->id);
        $this->assertSame(
            [$baseOrder->kitchenTicket->id, $addonOrder->kitchenTicket->id],
            KitchenTicket::query()->fifo()->pluck('id')->all()
        );
    }

    private function orderFixture(array $itemOverrides = []): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Order Tenant',
            'slug' => 'order-tenant-'.uniqid(),
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

        $item = MenuItem::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Menu Item',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ], $itemOverrides));

        return [$tenant, $waiter, $item];
    }
}
