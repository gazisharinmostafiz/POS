<?php

namespace App\Services\Orders;

use App\Events\KitchenTicketCreated;
use App\Events\OrderCreated;
use App\Events\TableStatusChanged;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Printing\PrintService;
use App\Support\OrderSourceTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(Tenant $tenant, User $waiter, array $payload): Order
    {
        $sourceType = $payload['source_type'] ?? null;

        if (! $sourceType || ! OrderSourceTypes::isValid($sourceType)) {
            throw new \InvalidArgumentException('Invalid order source type.');
        }

        if (OrderSourceTypes::requiresTable($sourceType) && empty($payload['table_number'])) {
            throw new \InvalidArgumentException('Table orders require a table number.');
        }

        if (! OrderSourceTypes::requiresTable($sourceType)) {
            $payload['table_number'] = null;
        }

        if (empty($payload['items']) || ! is_array($payload['items'])) {
            throw new \InvalidArgumentException('Order requires at least one item.');
        }

        $order = DB::transaction(function () use ($tenant, $waiter, $payload) {
            $order = Order::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $payload['branch_id'] ?? $waiter->branch_id,
                'order_number' => $this->generateOrderNumber($tenant),
                'source_type' => $payload['source_type'],
                'table_number' => $payload['table_number'] ?? null,
                'waiter_id' => $waiter->id,
                'cashier_id' => $payload['cashier_id'] ?? null,
                'order_status' => Order::STATUS_SENT_TO_KITCHEN,
                'payment_status' => Order::PAYMENT_UNPAID,
                'is_addon' => (bool) ($payload['is_addon'] ?? false),
                'kitchen_note' => $payload['kitchen_note'] ?? null,
                'subtotal' => 0,
                'discount_total' => 0,
                'service_charge_total' => 0,
                'tax_total' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;

            foreach ($payload['items'] as $itemPayload) {
                $menuItem = MenuItem::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($itemPayload['menu_item_id'] ?? null)
                    ->visibleToWaiter()
                    ->firstOrFail();

                $quantity = max(1, (int) ($itemPayload['quantity'] ?? 1));
                $unitPrice = (float) $menuItem->price;
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $order->items()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $order->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'item_name_snapshot' => $menuItem->name,
                    'unit_price_snapshot' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'item_note' => $itemPayload['item_note'] ?? null,
                ]);
            }

            $order->forceFill([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ])->save();

            $order->kitchenTicket()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $order->branch_id,
                'ticket_number' => $this->generateKitchenTicketNumber($tenant),
                'status' => KitchenTicket::STATUS_PENDING,
                'is_addon' => $order->is_addon,
                'kitchen_note' => $order->kitchen_note,
            ]);

            if ($order->source_type === OrderSourceTypes::TABLE) {
                RestaurantTable::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('number', $order->table_number)
                    ->update(['status' => RestaurantTable::STATUS_ORDER_SENT]);
            }

            return $order->fresh(['items', 'kitchenTicket']);
        });

        event(new OrderCreated($order));
        event(new KitchenTicketCreated($order->kitchenTicket));

        try {
            app(PrintService::class)->queueKitchenTicket($order->kitchenTicket);
        } catch (\Throwable) {
            // Printing must never block order creation.
        }

        if ($order->source_type === OrderSourceTypes::TABLE) {
            $table = RestaurantTable::query()
                ->where('tenant_id', $tenant->id)
                ->where('number', $order->table_number)
                ->first();

            if ($table) {
                event(new TableStatusChanged($table));
            }
        }

        return $order;
    }

    private function generateOrderNumber(Tenant $tenant): string
    {
        do {
            $number = 'ORD-'.$tenant->id.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function generateKitchenTicketNumber(Tenant $tenant): string
    {
        do {
            $number = 'KIT-'.$tenant->id.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (KitchenTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
