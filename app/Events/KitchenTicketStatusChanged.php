<?php

namespace App\Events;

use App\Models\KitchenTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public KitchenTicket $ticket)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->ticket->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'KitchenTicketStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->ticket->tenant_id,
            'branch_id' => $this->ticket->branch_id,
            'ticket_id' => $this->ticket->id,
            'order_id' => $this->ticket->order_id,
            'status' => $this->ticket->status,
        ];
    }
}
