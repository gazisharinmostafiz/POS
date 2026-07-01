<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->order->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'OrderUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->order->tenant_id,
            'branch_id' => $this->order->branch_id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'source_type' => $this->order->source_type,
            'table_number' => $this->order->table_number,
            'order_status' => $this->order->order_status,
            'payment_status' => $this->order->payment_status,
        ];
    }
}
