<?php

namespace App\Http\Controllers\Tenant;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Order;
use App\Support\ChatRooms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function rooms(Request $request): JsonResponse
    {
        return response()->json([
            'rooms' => collect(ChatRooms::roomsFor($request->user()))
                ->map(fn (string $room) => [
                    'key' => $room,
                    'label' => ChatRooms::label($room),
                    'unread_count' => $this->unreadCount($request, $room),
                ])
                ->values(),
        ]);
    }

    public function index(Request $request, string $room): JsonResponse
    {
        abort_unless(ChatRooms::canAccess($request->user(), $room), 403);

        $messages = ChatMessage::query()
            ->with('sender:id,name,role')
            ->forTenant(current_tenant())
            ->where('room', $room)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isNotEmpty()) {
            $this->markRead($request, $room, $messages->last()->id);
        }

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $message) => $this->messagePayload($message))->values(),
        ]);
    }

    public function store(Request $request, string $room): JsonResponse
    {
        abort_unless(ChatRooms::canSend($request->user(), $room), 403);

        $payload = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'related_order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        if (! empty($payload['related_order_id'])) {
            abort_unless(
                Order::query()
                    ->where('tenant_id', current_tenant()->id)
                    ->whereKey($payload['related_order_id'])
                    ->exists(),
                404
            );
        }

        $message = ChatMessage::query()->create([
            'tenant_id' => current_tenant()->id,
            'branch_id' => current_branch()?->id ?? $request->user()->branch_id,
            'room' => $room,
            'sender_id' => $request->user()->id,
            'related_order_id' => $payload['related_order_id'] ?? null,
            'message' => $payload['message'],
        ]);

        $this->markRead($request, $room, $message->id);

        event(new ChatMessageSent($message->load('sender:id,name,role')));

        return response()->json([
            'message' => $this->messagePayload($message),
        ], 201);
    }

    private function unreadCount(Request $request, string $room): int
    {
        $lastReadId = ChatRead::query()
            ->where('tenant_id', current_tenant()->id)
            ->where('user_id', $request->user()->id)
            ->where('room', $room)
            ->value('last_read_message_id') ?? 0;

        return ChatMessage::query()
            ->forTenant(current_tenant())
            ->where('room', $room)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('id', '>', $lastReadId)
            ->count();
    }

    private function markRead(Request $request, string $room, int $messageId): void
    {
        ChatRead::query()->updateOrCreate(
            [
                'tenant_id' => current_tenant()->id,
                'user_id' => $request->user()->id,
                'room' => $room,
            ],
            [
                'last_read_message_id' => $messageId,
            ]
        );
    }

    private function messagePayload(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'room' => $message->room,
            'message' => $message->message,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->sender?->role,
            'related_order_id' => $message->related_order_id,
            'timestamp' => $message->created_at?->toIso8601String(),
        ];
    }
}
