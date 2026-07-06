<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'poslab')->firstOrFail();
        $branch = $tenant->branches()->where('slug', 'main')->first();

        app(RestaurantSettingsService::class)->save($tenant, [
            'restaurant_name' => 'PosLAB Demo Restaurant',
            'logo_text' => 'PosLAB',
            'address' => '24 Demo High Street, London',
            'phone' => '020 7946 0123',
            'email' => 'demo@poslab.test',
            'website' => 'https://poslab.test',
            'currency_symbol' => 'GBP ',
            'currency_code' => 'GBP',
            'service_charge_percent' => 10,
            'tax_vat_percent' => 20,
            'table_count' => 8,
            'invoice_footer' => 'Thank you for visiting PosLAB Demo Restaurant.',
            'theme_color' => '#0891b2',
        ]);

        $categories = collect([
            ['name' => 'Starters', 'description' => 'Small plates and snacks', 'sort_order' => 1],
            ['name' => 'Mains', 'description' => 'House favourites', 'sort_order' => 2],
            ['name' => 'Drinks', 'description' => 'Soft drinks and hot drinks', 'sort_order' => 3],
            ['name' => 'Desserts', 'description' => 'Sweet plates', 'sort_order' => 4],
        ])->mapWithKeys(function (array $category) use ($tenant, $branch) {
            $model = Category::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $category['name']],
                [
                    'branch_id' => null,
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );

            return [$category['name'] => $model];
        });

        $items = [
            ['Starters', 'Crispy Calamari', 'Lemon aioli and chilli salt', 7.95, 1],
            ['Starters', 'Garlic Mushrooms', 'Creamy garlic sauce on toasted sourdough', 6.50, 2],
            ['Starters', 'Halloumi Bites', 'Honey glaze and sesame', 6.95, 3],
            ['Mains', 'Classic Beef Burger', 'Cheddar, lettuce, tomato, house sauce', 13.95, 1],
            ['Mains', 'Chicken Katsu Curry', 'Steamed rice and pickled salad', 14.50, 2],
            ['Mains', 'Margherita Pizza', 'Tomato, mozzarella, basil', 11.95, 3],
            ['Mains', 'Grilled Salmon', 'New potatoes, greens, lemon butter', 18.95, 4],
            ['Drinks', 'Coca-Cola', '330ml bottle', 2.95, 1],
            ['Drinks', 'Fresh Orange Juice', 'Pressed orange juice', 3.75, 2],
            ['Drinks', 'Americano', 'Freshly brewed coffee', 2.80, 3],
            ['Desserts', 'Sticky Toffee Pudding', 'Vanilla ice cream', 6.95, 1],
            ['Desserts', 'Chocolate Brownie', 'Warm brownie and cream', 6.50, 2],
        ];

        foreach ($items as [$categoryName, $name, $description, $price, $sortOrder]) {
            MenuItem::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'branch_id' => null,
                    'category_id' => $categories[$categoryName]->id,
                    'description' => $description,
                    'price' => $price,
                    'image_path' => null,
                    'is_available' => true,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }

        $this->createPrinters($tenant);
        $this->createSampleOrders($tenant);
    }

    private function createPrinters(Tenant $tenant): void
    {
        foreach ([
            ['Demo Browser Receipt', Printer::ROLE_RECEIPT],
            ['Demo Browser Kitchen', Printer::ROLE_KITCHEN],
        ] as [$name, $role]) {
            Printer::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'branch_id' => null,
                    'type' => Printer::TYPE_BROWSER,
                    'role' => $role,
                    'paper_size' => '80mm',
                    'connection_settings' => ['demo' => true],
                    'kitchen_category_routes' => [],
                    'is_active' => true,
                ]
            );
        }
    }

    private function createSampleOrders(Tenant $tenant): void
    {
        if (Order::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $waiter = User::query()->where('tenant_id', $tenant->id)->where('role', Roles::WAITER)->firstOrFail();
        $counter = User::query()->where('tenant_id', $tenant->id)->where('role', Roles::COUNTER)->firstOrFail();
        $items = MenuItem::query()->where('tenant_id', $tenant->id)->pluck('id', 'name');
        $orders = app(OrderService::class);

        $tableOne = $orders->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'kitchen_note' => 'Demo: no onions on burger.',
            'items' => [
                ['menu_item_id' => $items['Classic Beef Burger'], 'quantity' => 2],
                ['menu_item_id' => $items['Coca-Cola'], 'quantity' => 2],
            ],
        ]);
        $this->setKitchenState($tableOne, KitchenTicket::STATUS_READY, Order::STATUS_READY, RestaurantTable::STATUS_READY);

        $addon = $orders->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'is_addon' => true,
            'kitchen_note' => 'Demo add-on dessert.',
            'items' => [
                ['menu_item_id' => $items['Sticky Toffee Pudding'], 'quantity' => 1],
            ],
        ]);
        $this->setKitchenState($addon, KitchenTicket::STATUS_PENDING, Order::STATUS_SENT_TO_KITCHEN, RestaurantTable::STATUS_ORDER_SENT);

        $tableTwo = $orders->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 2,
            'kitchen_note' => 'Demo: salmon sauce on side.',
            'items' => [
                ['menu_item_id' => $items['Grilled Salmon'], 'quantity' => 1],
                ['menu_item_id' => $items['Fresh Orange Juice'], 'quantity' => 1],
            ],
        ]);
        $this->setKitchenState($tableTwo, KitchenTicket::STATUS_COOKING, Order::STATUS_COOKING, RestaurantTable::STATUS_COOKING);

        $takeaway = $orders->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [
                ['menu_item_id' => $items['Chicken Katsu Curry'], 'quantity' => 1],
                ['menu_item_id' => $items['Chocolate Brownie'], 'quantity' => 1],
            ],
        ]);
        $this->setKitchenState($takeaway, KitchenTicket::STATUS_READY, Order::STATUS_READY, null);

        app(BillingService::class)->recordPayment(
            $tenant,
            $takeaway,
            $counter,
            10,
            20,
            [
                'provider' => 'external_card',
                'manual_reference' => 'DEMO-CARD-001',
            ]
        );
    }

    private function setKitchenState(Order $order, string $ticketStatus, string $orderStatus, ?string $tableStatus): void
    {
        $order->kitchenTicket?->forceFill(['status' => $ticketStatus])->save();
        $order->forceFill(['order_status' => $orderStatus])->save();

        if ($tableStatus && $order->table_number) {
            RestaurantTable::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('number', $order->table_number)
                ->first()
                ?->updateStatus($tableStatus);
        }
    }
}
