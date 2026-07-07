<?php

namespace App\Services\Billing;

use App\Events\OrderUpdated;
use App\Events\PaymentCompleted;
use App\Events\TableStatusChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\SplitBill;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Settings\RestaurantSettingsService;
use App\Services\Payments\PaymentService;
use App\Services\Printing\PrintService;
use App\Support\OrderSourceTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public const DISCOUNT_FLAT = 'flat';
    public const DISCOUNT_PERCENT = 'percent';

    public function billOrders(Tenant $tenant, Order $selectedOrder): Collection
    {
        if ($selectedOrder->source_type !== OrderSourceTypes::TABLE) {
            return Order::query()
                ->with(['items', 'kitchenTicket:id,order_id,status,is_addon,created_at'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($selectedOrder->id)
                ->get();
        }

        return Order::query()
            ->with(['items', 'kitchenTicket:id,order_id,status,is_addon,created_at'])
            ->where('tenant_id', $tenant->id)
            ->where('source_type', OrderSourceTypes::TABLE)
            ->where('table_number', $selectedOrder->table_number)
            ->where('payment_status', '!=', Order::PAYMENT_PAID)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function billPayload(Tenant $tenant, Order $selectedOrder): array
    {
        return $this->payloadFromOrders($selectedOrder, $this->billOrders($tenant, $selectedOrder), $this->settings($tenant));
    }

    public function applyDiscount(Tenant $tenant, Order $order, User $user, string $type, float $value, ?string $reason = null): Order
    {
        $order = Order::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($order->id)
            ->firstOrFail();

        $discountTotal = match ($type) {
            self::DISCOUNT_FLAT => min($value, (float) $order->subtotal),
            self::DISCOUNT_PERCENT => round(((float) $order->subtotal) * (min($value, 100) / 100), 2),
            default => throw new \InvalidArgumentException('Invalid discount type.'),
        };

        $order->forceFill([
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_total' => $discountTotal,
            'discount_reason' => $reason,
            'discount_applied_by' => $user->id,
        ])->save();

        return $order->fresh(['items', 'kitchenTicket']);
    }

    public function splitBill(Tenant $tenant, Order $selectedOrder, User $cashier, int $peopleCount): SplitBill
    {
        $bill = $this->billPayload($tenant, $selectedOrder);
        $peopleCount = max(1, $peopleCount);
        $amountPerPerson = round($bill['total_payable'] / $peopleCount, 2);
        $roundingDifference = round($bill['total_payable'] - ($amountPerPerson * $peopleCount), 2);

        return SplitBill::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $selectedOrder->branch_id,
            'order_id' => $selectedOrder->id,
            'order_ids' => $bill['order_ids']->all(),
            'cashier_id' => $cashier->id,
            'people_count' => $peopleCount,
            'total_payable' => $bill['total_payable'],
            'amount_per_person' => $amountPerPerson,
            'rounding_difference' => $roundingDifference,
        ]);
    }

    public function recordPayment(
        Tenant $tenant,
        Order $selectedOrder,
        User $cashier,
        float $cashAmount,
        float $cardAmount,
        array $providerOptions = []
    ): Payment
    {
        return DB::transaction(function () use ($tenant, $selectedOrder, $cashier, $cashAmount, $cardAmount, $providerOptions) {
            $bill = $this->billPayload($tenant, $selectedOrder);
            $cashAmount = max(0, $cashAmount);
            $cardAmount = max(0, $cardAmount);
            $totalPaid = round($cashAmount + $cardAmount, 2);
            $totalPayable = $bill['total_payable'];
            $balance = round(max(0, $totalPayable - $totalPaid), 2);
            $change = round(max(0, $cashAmount - max(0, $totalPayable - $cardAmount)), 2);
            $status = $totalPaid >= $totalPayable
                ? Order::PAYMENT_PAID
                : ($totalPaid > 0 ? Order::PAYMENT_PARTIAL : Order::PAYMENT_UNPAID);

            $payment = Payment::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $selectedOrder->branch_id,
                'order_id' => $selectedOrder->id,
                'order_ids' => $bill['order_ids']->all(),
                'cashier_id' => $cashier->id,
                'cash_amount' => $cashAmount,
                'card_amount' => $cardAmount,
                'total_paid' => $totalPaid,
                'total_payable' => $totalPayable,
                'balance' => $balance,
                'change_amount' => $change,
                'status' => $status,
            ]);

            $providerResult = null;

            if ($cardAmount > 0) {
                $providerResult = app(PaymentService::class)->charge(
                    $tenant,
                    $selectedOrder,
                    $cashier,
                    $providerOptions['provider'] ?? 'external_card',
                    $cardAmount,
                    $providerOptions['terminal_id'] ?? null,
                    $providerOptions['manual_reference'] ?? null,
                    $providerOptions['metadata'] ?? []
                );
            } elseif ($cashAmount > 0) {
                $providerResult = app(PaymentService::class)->charge(
                    $tenant,
                    $selectedOrder,
                    $cashier,
                    'cash',
                    $cashAmount,
                    null,
                    null,
                    $providerOptions['metadata'] ?? []
                );
            }

            if ($providerResult) {
                $payment = app(PaymentService::class)->applyProviderResult($payment, $providerResult);
            }

            Order::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $bill['order_ids']->all())
                ->update([
                    'cashier_id' => $cashier->id,
                    'payment_status' => $status,
                ]);

            $orders = Order::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $bill['order_ids']->all())
                ->get();

            if ($status === Order::PAYMENT_PAID && $selectedOrder->source_type === OrderSourceTypes::TABLE) {
                $table = RestaurantTable::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('number', $selectedOrder->table_number)
                    ->first();

                if ($table) {
                    $table->updateStatus(RestaurantTable::STATUS_PAID);
                    event(new TableStatusChanged($table));
                }
            }

            event(new PaymentCompleted($payment));

            try {
                app(PrintService::class)->queueReceipt($payment);
            } catch (\Throwable) {
                // Printing must never block payment completion.
            }

            foreach ($orders as $order) {
                event(new OrderUpdated($order));
            }

            return $payment;
        });
    }

    private function payloadFromOrders(Order $selectedOrder, Collection $orders, array $settings): array
    {
        $items = $orders->flatMap(function (Order $order) {
            return $order->items->map(fn ($item) => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'is_addon' => $order->is_addon,
                'name' => $item->item_name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price_snapshot,
                'line_total' => (float) $item->line_total,
            ]);
        })->values();

        $subtotal = round((float) $items->sum('line_total'), 2);
        $discountTotal = round((float) $orders->sum('discount_total'), 2);
        $discountedSubtotal = round(max(0, $subtotal - $discountTotal), 2);
        $serviceChargePercent = ($settings['service_charge_enabled'] ?? true)
            ? (float) ($settings['service_charge_percent'] ?? 0)
            : 0;
        $taxVatPercent = ($settings['tax_enabled'] ?? true)
            ? (float) ($settings['tax_vat_percent'] ?? 0)
            : 0;
        $serviceCharge = round($discountedSubtotal * ($serviceChargePercent / 100), 2);
        $taxVat = round($discountedSubtotal * ($taxVatPercent / 100), 2);

        return [
            'selected_order_id' => $selectedOrder->id,
            'source_type' => $selectedOrder->source_type,
            'table_number' => $selectedOrder->table_number,
            'order_ids' => $orders->pluck('id')->values(),
            'order_numbers' => $orders->pluck('order_number')->values(),
            'items' => $items,
            'currency_symbol' => $settings['currency_symbol'] ?? '£',
            'service_charge_percent' => $serviceChargePercent,
            'tax_vat_percent' => $taxVatPercent,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'discounted_subtotal' => $discountedSubtotal,
            'service_charge_total' => $serviceCharge,
            'tax_vat_total' => $taxVat,
            'total_payable' => round($discountedSubtotal + $serviceCharge + $taxVat, 2),
        ];
    }

    private function settings(Tenant $tenant): array
    {
        return app(RestaurantSettingsService::class)->get($tenant);
    }
}
