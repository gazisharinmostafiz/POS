<?php

namespace App\Http\Controllers\Kitchen;

use App\Events\KitchenTicketStatusChanged;
use App\Events\OrderUpdated;
use App\Events\TableStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenDisplayController extends Controller
{
    public function data(): JsonResponse
    {
        $tickets = KitchenTicket::query()
            ->with([
                'order.waiter:id,name',
                'order.items:id,order_id,item_name_snapshot,quantity,item_note',
            ])
            ->where('tenant_id', current_tenant()->id)
            ->whereIn('status', [
                KitchenTicket::STATUS_PENDING,
                KitchenTicket::STATUS_COOKING,
                KitchenTicket::STATUS_READY,
            ])
            ->fifo()
            ->get();

        return response()->json([
            'columns' => [
                KitchenTicket::STATUS_PENDING => $tickets
                    ->where('status', KitchenTicket::STATUS_PENDING)
                    ->values()
                    ->map(fn (KitchenTicket $ticket) => $this->ticketPayload($ticket)),
                KitchenTicket::STATUS_COOKING => $tickets
                    ->where('status', KitchenTicket::STATUS_COOKING)
                    ->values()
                    ->map(fn (KitchenTicket $ticket) => $this->ticketPayload($ticket)),
                KitchenTicket::STATUS_READY => $tickets
                    ->where('status', KitchenTicket::STATUS_READY)
                    ->values()
                    ->map(fn (KitchenTicket $ticket) => $this->ticketPayload($ticket)),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $ticket): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'in:'.implode(',', [
                KitchenTicket::STATUS_PENDING,
                KitchenTicket::STATUS_COOKING,
                KitchenTicket::STATUS_READY,
            ])],
        ]);

        $ticket = KitchenTicket::query()
            ->with('order.items', 'order.waiter:id,name')
            ->where('tenant_id', current_tenant()->id)
            ->findOrFail($ticket);

        $ticket->forceFill(['status' => $payload['status']])->save();
        $table = $this->syncOrderAndTableStatus($ticket);
        $ticket = $ticket->fresh(['order.items', 'order.waiter:id,name']);

        event(new KitchenTicketStatusChanged($ticket));
        event(new OrderUpdated($ticket->order));

        if ($table) {
            event(new TableStatusChanged($table));
        }

        return response()->json([
            'ticket' => $this->ticketPayload($ticket),
        ]);
    }

    private function syncOrderAndTableStatus(KitchenTicket $ticket): ?RestaurantTable
    {
        $orderStatus = match ($ticket->status) {
            KitchenTicket::STATUS_COOKING => Order::STATUS_COOKING,
            KitchenTicket::STATUS_READY => Order::STATUS_READY,
            default => Order::STATUS_SENT_TO_KITCHEN,
        };

        $ticket->order->forceFill(['order_status' => $orderStatus])->save();

        if ($ticket->order->source_type !== 'table' || ! $ticket->order->table_number) {
            return null;
        }

        $tableStatus = match ($ticket->status) {
            KitchenTicket::STATUS_COOKING => RestaurantTable::STATUS_COOKING,
            KitchenTicket::STATUS_READY => RestaurantTable::STATUS_READY,
            default => RestaurantTable::STATUS_ORDER_SENT,
        };

        $table = RestaurantTable::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('number', $ticket->order->table_number)
            ->first();

        if (! $table) {
            return null;
        }

        $table->forceFill(['status' => $tableStatus])->save();

        return $table;
    }

    private function ticketPayload(KitchenTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'is_addon' => $ticket->is_addon,
            'kitchen_note' => $ticket->kitchen_note,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'ordered_time' => $ticket->created_at?->format('H:i'),
            'order' => [
                'id' => $ticket->order->id,
                'order_number' => $ticket->order->order_number,
                'source_type' => $ticket->order->source_type,
                'table_number' => $ticket->order->table_number,
                'waiter_name' => $ticket->order->waiter?->name,
                'items' => $ticket->order->items->map(fn ($item) => [
                    'name' => $item->item_name_snapshot,
                    'quantity' => $item->quantity,
                    'note' => $item->item_note,
                ])->values(),
            ],
        ];
    }
}
