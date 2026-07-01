<?php

namespace App\Http\Controllers\Counter;

use App\Exceptions\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillingService;
use App\Services\Settings\RestaurantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterBillingController extends Controller
{
    public function data(): JsonResponse
    {
        $settings = app(RestaurantSettingsService::class)->get(current_tenant());

        $orders = Order::query()
            ->withCount('items')
            ->with('kitchenTicket:id,order_id,status,is_addon,created_at')
            ->forTenant(current_tenant())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'orders' => $orders->map(fn (Order $order) => $this->orderSummaryPayload($order))->values(),
            'settings' => [
                'currency_symbol' => $settings['currency_symbol'] ?? '£',
                'service_charge_percent' => (float) ($settings['service_charge_percent'] ?? 0),
                'tax_vat_percent' => (float) ($settings['tax_vat_percent'] ?? 0),
            ],
        ]);
    }

    public function bill(int $order, BillingService $billing): JsonResponse
    {
        $order = Order::query()
            ->forTenant(current_tenant())
            ->findOrFail($order);

        return response()->json([
            'bill' => $billing->billPayload(current_tenant(), $order),
        ]);
    }

    public function discount(Request $request, int $order, BillingService $billing): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'in:flat,percent'],
            'value' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::query()
            ->forTenant(current_tenant())
            ->findOrFail($order);

        $order = $billing->applyDiscount(
            current_tenant(),
            $order,
            $request->user(),
            $payload['type'],
            (float) $payload['value'],
            $payload['reason'] ?? null
        );

        return response()->json([
            'order' => [
                'id' => $order->id,
                'discount_type' => $order->discount_type,
                'discount_value' => (float) $order->discount_value,
                'discount_total' => (float) $order->discount_total,
                'discount_reason' => $order->discount_reason,
                'discount_applied_by' => $order->discount_applied_by,
            ],
            'bill' => $billing->billPayload(current_tenant(), $order),
        ]);
    }

    public function split(Request $request, int $order, BillingService $billing): JsonResponse
    {
        $payload = $request->validate([
            'people_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $order = Order::query()
            ->forTenant(current_tenant())
            ->findOrFail($order);

        $split = $billing->splitBill(current_tenant(), $order, $request->user(), (int) $payload['people_count']);

        return response()->json([
            'split_bill' => [
                'id' => $split->id,
                'order_ids' => $split->order_ids,
                'people_count' => $split->people_count,
                'total_payable' => (float) $split->total_payable,
                'amount_per_person' => (float) $split->amount_per_person,
                'rounding_difference' => (float) $split->rounding_difference,
            ],
        ], 201);
    }

    public function payment(Request $request, int $order, BillingService $billing): JsonResponse
    {
        $payload = $request->validate([
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'card_amount' => ['nullable', 'numeric', 'min:0'],
            'provider' => ['nullable', 'string', 'max:100'],
            'terminal_id' => ['nullable', 'string', 'max:255'],
            'manual_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::query()
            ->forTenant(current_tenant())
            ->findOrFail($order);

        try {
            $payment = $billing->recordPayment(
                current_tenant(),
                $order,
                $request->user(),
                (float) ($payload['cash_amount'] ?? 0),
                (float) ($payload['card_amount'] ?? 0),
                [
                    'provider' => $payload['provider'] ?? 'external_card',
                    'terminal_id' => $payload['terminal_id'] ?? null,
                    'manual_reference' => $payload['manual_reference'] ?? null,
                ]
            );
        } catch (PaymentProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'provider' => $exception->provider,
            ], 422);
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'order_ids' => $payment->order_ids,
                'cash_amount' => (float) $payment->cash_amount,
                'card_amount' => (float) $payment->card_amount,
                'total_paid' => (float) $payment->total_paid,
                'total_payable' => (float) $payment->total_payable,
                'balance' => (float) $payment->balance,
                'change' => (float) $payment->change_amount,
                'status' => $payment->status,
                'provider' => $payment->provider,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'terminal_id' => $payment->terminal_id,
            ],
        ], 201);
    }

    private function orderSummaryPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'created_at' => $order->created_at?->toIso8601String(),
            'time' => $order->created_at?->format('H:i'),
            'order_number' => $order->order_number,
            'source_type' => $order->source_type,
            'table_number' => $order->table_number,
            'is_addon' => $order->is_addon,
            'item_count' => $order->items_count,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'amount' => (float) $order->total,
            'kitchen_status' => $order->kitchenTicket?->status,
        ];
    }
}
