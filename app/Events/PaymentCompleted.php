<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->payment->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'PaymentCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->payment->tenant_id,
            'branch_id' => $this->payment->branch_id,
            'payment_id' => $this->payment->id,
            'order_ids' => $this->payment->order_ids,
            'status' => $this->payment->status,
            'total_paid' => (float) $this->payment->total_paid,
            'balance' => (float) $this->payment->balance,
            'change' => (float) $this->payment->change_amount,
        ];
    }
}
