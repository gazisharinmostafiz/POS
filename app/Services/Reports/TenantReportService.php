<?php

namespace App\Services\Reports;

use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OrderSourceTypes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TenantReportService
{
    public function dashboard(Tenant $tenant, array $filters = []): array
    {
        [$start, $end] = $this->dateRange($filters + ['period' => $filters['period'] ?? 'today']);

        $orders = Order::query()->forTenant($tenant);
        $this->applyOrderFilters($orders, $filters, $start, $end);

        $payments = Payment::query()->where('tenant_id', $tenant->id);
        $this->applyPaymentFilters($payments, $filters, $start, $end);

        $totalOrders = (clone $orders)->count();
        $totalSales = (float) (clone $payments)->sum('total_payable');
        $topSellingItem = $this->itemSales($tenant, $filters, $start, $end)[0] ?? null;

        return [
            'total_sales_today' => round($totalSales, 2),
            'total_orders_today' => $totalOrders,
            'cash_collected' => round((float) (clone $payments)->sum('cash_amount'), 2),
            'card_collected' => round((float) (clone $payments)->sum('card_amount'), 2),
            'discount_total' => round((float) (clone $orders)->sum('discount_total'), 2),
            'pending_bills' => (clone $orders)->where('payment_status', '!=', Order::PAYMENT_PAID)->count(),
            'active_kitchen_tickets' => KitchenTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', [KitchenTicket::STATUS_PENDING, KitchenTicket::STATUS_COOKING])
                ->count(),
            'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            'top_selling_item' => $topSellingItem['item_name'] ?? null,
            'top_selling_item_quantity' => $topSellingItem['quantity'] ?? 0,
        ];
    }

    public function reports(Tenant $tenant, array $filters = []): array
    {
        [$start, $end] = $this->dateRange($filters);

        return [
            'dashboard' => $this->dashboard($tenant, $filters),
            'sales_report' => $this->salesReport($tenant, $filters, $start, $end),
            'item_sales_report' => $this->itemSales($tenant, $filters, $start, $end),
            'cash_card_report' => $this->cashCardReport($tenant, $filters, $start, $end),
            'discount_report' => $this->discountReport($tenant, $filters, $start, $end),
            'staff_report' => [
                'placeholder' => true,
                'message' => 'Staff performance metrics will be expanded in a later phase.',
            ],
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ];
    }

    public function filterOptions(Tenant $tenant): array
    {
        return [
            'waiters' => User::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('id')
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->values(),
            'cashiers' => User::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('id')
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->values(),
            'source_types' => OrderSourceTypes::ALL,
        ];
    }

    private function salesReport(Tenant $tenant, array $filters, Carbon $start, Carbon $end): array
    {
        $orders = Order::query()
            ->with(['waiter:id,name', 'cashier:id,name'])
            ->forTenant($tenant);
        $this->applyOrderFilters($orders, $filters, $start, $end);

        return $orders
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at?->toDateString(),
                'source_type' => $order->source_type,
                'table_number' => $order->table_number,
                'waiter_name' => $order->waiter?->name,
                'cashier_name' => $order->cashier?->name,
                'payment_status' => $order->payment_status,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'total' => (float) $order->total,
            ])
            ->values()
            ->all();
    }

    private function itemSales(Tenant $tenant, array $filters, Carbon $start, Carbon $end): array
    {
        $query = OrderItem::query()
            ->select([
                'order_items.item_name_snapshot as item_name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.line_total) as sales_total'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.tenant_id', $tenant->id)
            ->groupBy('order_items.item_name_snapshot')
            ->orderByDesc('quantity')
            ->orderBy('item_name');

        $this->applyJoinedOrderFilters($query, $filters, $start, $end);

        return $query->get()
            ->map(fn ($row) => [
                'item_name' => $row->item_name,
                'quantity' => (int) $row->quantity,
                'sales_total' => round((float) $row->sales_total, 2),
            ])
            ->values()
            ->all();
    }

    private function cashCardReport(Tenant $tenant, array $filters, Carbon $start, Carbon $end): array
    {
        $payments = Payment::query()->where('tenant_id', $tenant->id);
        $this->applyPaymentFilters($payments, $filters, $start, $end);

        return [
            'cash_collected' => round((float) (clone $payments)->sum('cash_amount'), 2),
            'card_collected' => round((float) (clone $payments)->sum('card_amount'), 2),
            'total_paid' => round((float) (clone $payments)->sum('total_paid'), 2),
            'payment_count' => (clone $payments)->count(),
        ];
    }

    private function discountReport(Tenant $tenant, array $filters, Carbon $start, Carbon $end): array
    {
        $orders = Order::query()
            ->with('waiter:id,name')
            ->forTenant($tenant)
            ->where('discount_total', '>', 0);
        $this->applyOrderFilters($orders, $filters, $start, $end);

        return $orders->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'discount_type' => $order->discount_type,
                'discount_value' => (float) $order->discount_value,
                'discount_total' => (float) $order->discount_total,
                'discount_reason' => $order->discount_reason,
                'applied_by' => $order->discount_applied_by,
            ])
            ->values()
            ->all();
    }

    private function dateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'today';

        return match ($period) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                Carbon::parse($filters['start_date'] ?? now())->startOfDay(),
                Carbon::parse($filters['end_date'] ?? now())->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function applyOrderFilters(Builder $query, array $filters, Carbon $start, Carbon $end): void
    {
        $query->whereBetween('created_at', [$start, $end]);

        if (! empty($filters['waiter_id'])) {
            $query->where('waiter_id', $filters['waiter_id']);
        }

        if (! empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['payment_method'])) {
            $method = $filters['payment_method'];
            $query->whereHas('payments', fn (Builder $payment) => $method === 'cash'
                ? $payment->where('cash_amount', '>', 0)
                : $payment->where('card_amount', '>', 0)
            );
        }
    }

    private function applyJoinedOrderFilters($query, array $filters, Carbon $start, Carbon $end): void
    {
        $query->whereBetween('orders.created_at', [$start, $end]);

        if (! empty($filters['waiter_id'])) {
            $query->where('orders.waiter_id', $filters['waiter_id']);
        }

        if (! empty($filters['cashier_id'])) {
            $query->where('orders.cashier_id', $filters['cashier_id']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('orders.source_type', $filters['source_type']);
        }

        if (! empty($filters['payment_method'])) {
            $method = $filters['payment_method'];
            $query->whereExists(function ($exists) use ($method) {
                $exists->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id');

                $method === 'cash'
                    ? $exists->where('payments.cash_amount', '>', 0)
                    : $exists->where('payments.card_amount', '>', 0);
            });
        }
    }

    private function applyPaymentFilters(Builder $query, array $filters, Carbon $start, Carbon $end): void
    {
        $query->whereBetween('created_at', [$start, $end]);

        if (! empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        if (($filters['payment_method'] ?? null) === 'cash') {
            $query->where('cash_amount', '>', 0);
        }

        if (($filters['payment_method'] ?? null) === 'card') {
            $query->where('card_amount', '>', 0);
        }

        if (! empty($filters['waiter_id']) || ! empty($filters['source_type'])) {
            $query->whereHas('order', function (Builder $order) use ($filters) {
                if (! empty($filters['waiter_id'])) {
                    $order->where('waiter_id', $filters['waiter_id']);
                }

                if (! empty($filters['source_type'])) {
                    $order->where('source_type', $filters['source_type']);
                }
            });
        }
    }
}
