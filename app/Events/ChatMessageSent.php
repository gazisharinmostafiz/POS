<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $chatMessage)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->chatMessage->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }

    public function broadcastWith(): array
    {
        $this->chatMessage->loadMissing('sender:id,name,role');

        return [
            'tenant_id' => $this->chatMessage->tenant_id,
            'branch_id' => $this->chatMessage->branch_id,
            'room' => $this->chatMessage->room,
            'message_id' => $this->chatMessage->id,
            'message' => $this->chatMessage->message,
            'sender_id' => $this->chatMessage->sender_id,
            'sender_name' => $this->chatMessage->sender?->name,
            'sender_role' => $this->chatMessage->sender?->role,
            'related_order_id' => $this->chatMessage->related_order_id,
            'timestamp' => $this->chatMessage->created_at?->toIso8601String(),
        ];
    }
}
