<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatRooms;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InternalChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_sends_to_kitchen(): void
    {
        [$tenant, $waiter, $kitchen] = $this->chatFixture();
        Event::fake([ChatMessageSent::class]);

        $response = $this->actingAs($waiter)
            ->postJson('/tenant/chat/rooms/'.ChatRooms::WAITER_KITCHEN.'/messages', [
                'message' => 'Table 4 needs sauce',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message.room', ChatRooms::WAITER_KITCHEN)
            ->assertJsonPath('message.message', 'Table 4 needs sauce')
            ->assertJsonPath('message.sender_name', $waiter->name)
            ->assertJsonPath('message.sender_role', Roles::WAITER);

        $this->assertDatabaseHas('chat_messages', [
            'tenant_id' => $tenant->id,
            'room' => ChatRooms::WAITER_KITCHEN,
            'sender_id' => $waiter->id,
            'message' => 'Table 4 needs sauce',
        ]);

        $this->actingAs($kitchen)
            ->getJson('/tenant/chat/rooms/'.ChatRooms::WAITER_KITCHEN.'/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.message', 'Table 4 needs sauce')
            ->assertJsonPath('messages.0.sender_role', Roles::WAITER);

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($tenant) {
            return $event->broadcastWith()['tenant_id'] === $tenant->id
                && $event->broadcastWith()['room'] === ChatRooms::WAITER_KITCHEN
                && $event->broadcastWith()['message'] === 'Table 4 needs sauce';
        });
    }

    public function test_counter_sees_counter_kitchen_room(): void
    {
        [, , , $counter] = $this->chatFixture();

        $this->actingAs($counter)
            ->getJson('/tenant/chat/rooms')
            ->assertOk()
            ->assertJsonFragment([
                'key' => ChatRooms::COUNTER_KITCHEN,
                'label' => ChatRooms::label(ChatRooms::COUNTER_KITCHEN),
                'unread_count' => 0,
            ])
            ->assertJsonMissing(['key' => ChatRooms::WAITER_KITCHEN]);
    }

    public function test_unread_count_updates(): void
    {
        [, , $kitchen, $counter] = $this->chatFixture();

        $this->actingAs($kitchen)
            ->postJson('/tenant/chat/rooms/'.ChatRooms::COUNTER_KITCHEN.'/messages', [
                'message' => 'Order ready for collection',
            ])
            ->assertCreated();

        $this->actingAs($counter)
            ->getJson('/tenant/chat/rooms')
            ->assertOk()
            ->assertJsonFragment([
                'key' => ChatRooms::COUNTER_KITCHEN,
                'unread_count' => 1,
            ]);

        $this->actingAs($counter)
            ->getJson('/tenant/chat/rooms/'.ChatRooms::COUNTER_KITCHEN.'/messages')
            ->assertOk();

        $this->actingAs($counter)
            ->getJson('/tenant/chat/rooms')
            ->assertOk()
            ->assertJsonFragment([
                'key' => ChatRooms::COUNTER_KITCHEN,
                'unread_count' => 0,
            ]);
    }

    public function test_tenant_isolation_works(): void
    {
        [$tenantA, $waiterA, $kitchenA] = $this->chatFixture();
        [$tenantB, $waiterB] = $this->chatFixture();

        ChatMessage::query()->create([
            'tenant_id' => $tenantB->id,
            'room' => ChatRooms::WAITER_KITCHEN,
            'sender_id' => $waiterB->id,
            'message' => 'Other tenant message',
        ]);

        $this->actingAs($waiterA)
            ->postJson('/tenant/chat/rooms/'.ChatRooms::WAITER_KITCHEN.'/messages', [
                'message' => 'Tenant A message',
            ])
            ->assertCreated();

        $this->actingAs($kitchenA)
            ->getJson('/tenant/chat/rooms/'.ChatRooms::WAITER_KITCHEN.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Tenant A message')
            ->assertJsonMissing(['message' => 'Other tenant message']);

        $this->assertDatabaseHas('chat_messages', [
            'tenant_id' => $tenantA->id,
            'message' => 'Tenant A message',
        ]);
    }

    private function chatFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Chat Tenant '.uniqid(),
            'slug' => 'chat-tenant-'.uniqid(),
        ]);

        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);
        $kitchen = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::KITCHEN,
        ]);
        $counter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
        ]);

        return [$tenant, $waiter, $kitchen, $counter];
    }
}
