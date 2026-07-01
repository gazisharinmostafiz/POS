<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenDisplayScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_queue_uses_fifo_sorting_for_base_and_add_on_orders(): void
    {
        [$tenant, $kitchen, $waiter, $item] = $this->kitchenFixture();

        $first = $this->createTicketedOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'created_at' => '2026-07-01 10:00:00',
        ]);
        $second = $this->createTicketedOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 2,
            'created_at' => '2026-07-01 10:05:00',
        ]);
        $addon = $this->createTicketedOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'is_addon' => true,
            'created_at' => '2026-07-01 10:10:00',
        ]);

        $response = $this->actingAs($kitchen)->getJson('/kitchen/data');

        $response->assertOk()
            ->assertJsonPath('columns.pending.0.id', $first->kitchenTicket->id)
            ->assertJsonPath('columns.pending.1.id', $second->kitchenTicket->id)
            ->assertJsonPath('columns.pending.2.id', $addon->kitchenTicket->id)
            ->assertJsonPath('columns.pending.0.order.table_number', 1)
            ->assertJsonPath('columns.pending.1.order.table_number', 2)
            ->assertJsonPath('columns.pending.2.order.table_number', 1)
            ->assertJsonMissingPath('columns.pending.0.order.payment_status')
            ->assertJsonMissingPath('columns.pending.0.order.total')
            ->assertJsonMissingPath('columns.pending.0.order.subtotal');
    }

    public function test_kitchen_can_update_ticket_status(): void
    {
        [$tenant, $kitchen, $waiter, $item] = $this->kitchenFixture();
        $order = $this->createTicketedOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'created_at' => '2026-07-01 10:00:00',
        ]);

        $this->actingAs($kitchen)
            ->patchJson("/kitchen/tickets/{$order->kitchenTicket->id}/status", [
                'status' => KitchenTicket::STATUS_COOKING,
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', KitchenTicket::STATUS_COOKING);

        $this->assertDatabaseHas('kitchen_tickets', [
            'id' => $order->kitchenTicket->id,
            'status' => KitchenTicket::STATUS_COOKING,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => Order::STATUS_COOKING,
        ]);
        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 1,
            'status' => RestaurantTable::STATUS_COOKING,
        ]);
    }

    public function test_kitchen_screen_is_kitchen_user_only(): void
    {
        [, , $waiter] = $this->kitchenFixture();

        $this->actingAs($waiter)
            ->get('/kitchen')
            ->assertForbidden();

        $this->actingAs($waiter)
            ->getJson('/kitchen/data')
            ->assertForbidden();
    }

    public function test_add_on_label_is_displayed(): void
    {
        [$tenant, $kitchen, $waiter, $item] = $this->kitchenFixture();
        $addon = $this->createTicketedOrder($tenant, $waiter, $item, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'is_addon' => true,
            'created_at' => '2026-07-01 10:10:00',
        ]);

        $this->actingAs($kitchen)
            ->get('/kitchen')
            ->assertOk()
            ->assertSee('ADD-ON');

        $this->actingAs($kitchen)
            ->getJson('/kitchen/data')
            ->assertOk()
            ->assertJsonPath('columns.pending.0.id', $addon->kitchenTicket->id)
            ->assertJsonPath('columns.pending.0.is_addon', true);
    }

    private function kitchenFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kitchen Tenant',
            'slug' => 'kitchen-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'table_count' => 2,
        ]);

        $kitchen = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::KITCHEN,
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

        return [$tenant, $kitchen, $waiter, $item];
    }

    private function createTicketedOrder(Tenant $tenant, User $waiter, MenuItem $item, array $overrides = []): Order
    {
        $createdAt = Carbon::parse($overrides['created_at'] ?? now());

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => $overrides['source_type'] ?? OrderSourceTypes::TABLE,
            'table_number' => $overrides['table_number'] ?? 1,
            'is_addon' => $overrides['is_addon'] ?? false,
            'kitchen_note' => 'No onions',
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
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

        return $order->fresh('kitchenTicket');
    }
}
