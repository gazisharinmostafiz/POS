<?php

namespace App\Events;

use App\Models\RestaurantTable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RestaurantTable $table)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->table->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'TableStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->table->tenant_id,
            'branch_id' => $this->table->branch_id,
            'table_id' => $this->table->id,
            'number' => $this->table->number,
            'status' => $this->table->status,
        ];
    }
}
