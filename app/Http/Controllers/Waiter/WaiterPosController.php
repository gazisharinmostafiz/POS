<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaiterPosController extends Controller
{
    public function data(): JsonResponse
    {
        $tenant = current_tenant();
        $branch = current_branch();
        $settings = app(RestaurantSettingsService::class)->get($tenant);

        $categories = Category::query()
            ->forTenant($tenant)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $menuItems = MenuItem::query()
            ->with('category:id,name')
            ->forTenant($tenant)
            ->forBranch($branch)
            ->visibleToWaiter()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'description', 'price', 'image_path', 'sort_order']);

        $tables = RestaurantTable::query()
            ->forTenant($tenant)
            ->where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'number', 'label', 'status']);

        return response()->json([
            'categories' => $categories,
            'menu_items' => $menuItems,
            'tables' => $tables,
            'currency_symbol' => $settings['currency_symbol'] ?? '£',
            'source_types' => [
                'table' => OrderSourceTypes::TABLE,
                'takeaway' => OrderSourceTypes::TAKEAWAY,
                'walkIn' => OrderSourceTypes::WALK_IN,
            ],
        ]);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $payload = $this->validatedOrderPayload($request);

        $order = $orders->createOrder(current_tenant(), $request->user(), $payload);

        return response()->json([
            'order' => $this->orderPayload($order),
        ], 201);
    }

    public function storeAddon(Request $request, OrderService $orders): JsonResponse
    {
        $payload = $this->validatedOrderPayload($request);
        $payload['is_addon'] = true;

        if (($payload['source_type'] ?? null) === OrderSourceTypes::TABLE) {
            $table = RestaurantTable::query()
                ->forTenant(current_tenant())
                ->where('number', $payload['table_number'] ?? null)
                ->whereIn('status', [
                    RestaurantTable::STATUS_OCCUPIED,
                    RestaurantTable::STATUS_ORDER_SENT,
                    RestaurantTable::STATUS_COOKING,
                    RestaurantTable::STATUS_READY,
                    RestaurantTable::STATUS_PENDING_BILL,
                ])
                ->first();

            abort_unless($table, 422, 'Add-on orders require an existing occupied table.');
        }

        $order = $orders->createOrder(current_tenant(), $request->user(), $payload);

        return response()->json([
            'order' => $this->orderPayload($order),
        ], 201);
    }

    public function activeTableOrder(int $tableNumber): JsonResponse
    {
        $order = Order::query()
            ->forTenant(current_tenant())
            ->where('source_type', OrderSourceTypes::TABLE)
            ->where('table_number', $tableNumber)
            ->where('payment_status', '!=', Order::PAYMENT_PAID)
            ->latest()
            ->first();

        return response()->json([
            'order' => $order ? $this->orderPayload($order->load('items', 'kitchenTicket')) : null,
        ]);
    }

    private function validatedOrderPayload(Request $request): array
    {
        return $request->validate([
            'source_type' => ['required', 'in:'.implode(',', OrderSourceTypes::ALL)],
            'table_number' => ['nullable', 'integer', 'min:1'],
            'kitchen_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.item_note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'source_type' => $order->source_type,
            'table_number' => $order->table_number,
            'is_addon' => $order->is_addon,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'kitchen_ticket_id' => $order->kitchenTicket?->id,
        ];
    }
}
