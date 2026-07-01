<?php

namespace Tests\Feature;

use App\Events\KitchenTicketCreated;
use App\Events\KitchenTicketStatusChanged;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\TableStatusChanged;
use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\BroadcastChannelAuthorization;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_authorization_is_tenant_and_branch_scoped(): void
    {
        [$tenantA, , $userA] = $this->realtimeFixture(Roles::WAITER);
        [$tenantB] = $this->realtimeFixture(Roles::WAITER);
        $branchA = $tenantA->branches()->create(['name' => 'Branch A', 'slug' => 'branch-a']);
        $branchB = $tenantA->branches()->create(['name' => 'Branch B', 'slug' => 'branch-b']);

        $userA->forceFill(['branch_id' => $branchA->id])->save();

        $this->assertTrue(BroadcastChannelAuthorization::tenant($userA, $tenantA->id));
        $this->assertFalse(BroadcastChannelAuthorization::tenant($userA, $tenantB->id));
        $this->assertTrue(BroadcastChannelAuthorization::tenantBranch($userA, $tenantA->id, $branchA->id));
        $this->assertFalse(BroadcastChannelAuthorization::tenantBranch($userA, $tenantA->id, $branchB->id));
        $this->assertFalse(BroadcastChannelAuthorization::tenantBranch($userA, $tenantB->id, $branchA->id));
    }

    public function test_order_event_broadcasts_on_tenant_private_channel(): void
    {
        [$tenant, $item, $waiter] = $this->realtimeFixture(Roles::WAITER);
        Event::fake([OrderCreated::class, KitchenTicketCreated::class, TableStatusChanged::class]);

        app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($tenant) {
            return $event->broadcastWith()['tenant_id'] === $tenant->id
                && $this->broadcastChannelName($event) === 'private-tenant.'.$tenant->id;
        });
        Event::assertDispatched(KitchenTicketCreated::class);
        Event::assertDispatched(TableStatusChanged::class);
    }

    public function test_realtime_events_are_tenant_isolated(): void
    {
        [$tenantA, $itemA, $waiterA] = $this->realtimeFixture(Roles::WAITER);
        [$tenantB] = $this->realtimeFixture(Roles::WAITER);
        Event::fake([OrderCreated::class]);

        app(OrderService::class)->createOrder($tenantA, $waiterA, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $itemA->id, 'quantity' => 1]],
        ]);

        Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->broadcastWith()['tenant_id'] === $tenantA->id);
        Event::assertNotDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->broadcastWith()['tenant_id'] === $tenantB->id);
    }

    public function test_kitchen_status_event_broadcasts(): void
    {
        [$tenant, $item, $waiter] = $this->realtimeFixture(Roles::WAITER);
        $kitchen = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::KITCHEN,
        ]);
        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        Event::fake([KitchenTicketStatusChanged::class, OrderUpdated::class, TableStatusChanged::class]);

        $this->actingAs($kitchen)
            ->patchJson("/kitchen/tickets/{$order->kitchenTicket->id}/status", [
                'status' => KitchenTicket::STATUS_READY,
            ])
            ->assertOk();

        Event::assertDispatched(KitchenTicketStatusChanged::class, function (KitchenTicketStatusChanged $event) use ($tenant, $order) {
            return $event->broadcastWith()['tenant_id'] === $tenant->id
                && $event->broadcastWith()['order_id'] === $order->id
                && $event->broadcastWith()['status'] === KitchenTicket::STATUS_READY;
        });
        Event::assertDispatched(OrderUpdated::class);
        Event::assertDispatched(TableStatusChanged::class);
    }

    private function realtimeFixture(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Realtime Tenant '.uniqid(),
            'slug' => 'realtime-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'table_count' => 1,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Realtime Item',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $item, $user];
    }

    private function broadcastChannelName(object $event): string
    {
        $channel = $event->broadcastOn()[0];

        return (string) $channel;
    }
}
