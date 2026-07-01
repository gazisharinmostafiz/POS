<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Orders\OrderService;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_and_card_totals_match_payments(): void
    {
        [$tenant, $admin, $waiter, $cashier, $item] = $this->reportFixture(55);
        $order = $this->createOrder($tenant, $waiter, $item, ['created_at' => now()]);
        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 20, 35);
        $payment->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        $this->actingAs($admin)
            ->getJson('/tenant/reports/data?period=today')
            ->assertOk()
            ->assertJsonPath('dashboard.cash_collected', 20)
            ->assertJsonPath('dashboard.card_collected', 35)
            ->assertJsonPath('cash_card_report.cash_collected', 20)
            ->assertJsonPath('cash_card_report.card_collected', 35)
            ->assertJsonPath('cash_card_report.total_paid', 55);
    }

    public function test_item_sales_match_order_items(): void
    {
        [$tenant, $admin, $waiter, , $item] = $this->reportFixture(12);
        $this->createOrder($tenant, $waiter, $item, ['quantity' => 2, 'created_at' => now()]);
        $this->createOrder($tenant, $waiter, $item, ['quantity' => 3, 'created_at' => now()]);

        $this->actingAs($admin)
            ->getJson('/tenant/reports/data?period=today')
            ->assertOk()
            ->assertJsonPath('item_sales_report.0.item_name', 'Report Item')
            ->assertJsonPath('item_sales_report.0.quantity', 5)
            ->assertJsonPath('item_sales_report.0.sales_total', 60)
            ->assertJsonPath('dashboard.top_selling_item', 'Report Item');
    }

    public function test_date_filter_works(): void
    {
        [$tenant, $admin, $waiter, , $item] = $this->reportFixture(10);
        $today = $this->createOrder($tenant, $waiter, $item, ['created_at' => now()]);
        $yesterday = $this->createOrder($tenant, $waiter, $item, ['created_at' => now()->subDay()]);

        $this->actingAs($admin)
            ->getJson('/tenant/reports/data?period=today')
            ->assertOk()
            ->assertJsonPath('dashboard.total_orders_today', 1)
            ->assertJsonPath('sales_report.0.order_id', $today->id)
            ->assertJsonMissing(['order_id' => $yesterday->id]);

        $this->actingAs($admin)
            ->getJson('/tenant/reports/data?period=yesterday')
            ->assertOk()
            ->assertJsonPath('dashboard.total_orders_today', 1)
            ->assertJsonPath('sales_report.0.order_id', $yesterday->id)
            ->assertJsonMissing(['order_id' => $today->id]);
    }

    public function test_dashboard_role_access_works(): void
    {
        [$tenant, $admin] = $this->reportFixture();
        $waiter = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::WAITER]);
        $kitchen = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::KITCHEN]);

        $this->actingAs($admin)
            ->get('/tenant/admin')
            ->assertOk()
            ->assertSee('Dashboard and reports');

        $this->actingAs($waiter)
            ->get('/tenant/admin')
            ->assertForbidden();

        $this->actingAs($kitchen)
            ->get('/tenant/reports/data')
            ->assertForbidden();
    }

    public function test_reports_are_tenant_scoped(): void
    {
        [$tenantA, $adminA, $waiterA, , $itemA] = $this->reportFixture(10);
        [$tenantB, , $waiterB, , $itemB] = $this->reportFixture(99);

        $visible = $this->createOrder($tenantA, $waiterA, $itemA, ['created_at' => now()]);
        $hidden = $this->createOrder($tenantB, $waiterB, $itemB, ['created_at' => now()]);

        $this->actingAs($adminA)
            ->getJson('/tenant/reports/data?period=today')
            ->assertOk()
            ->assertJsonPath('sales_report.0.order_id', $visible->id)
            ->assertJsonMissing(['order_id' => $hidden->id])
            ->assertJsonPath('item_sales_report.0.item_name', 'Report Item');
    }

    private function reportFixture(float $price = 12): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Report Tenant '.uniqid(),
            'slug' => 'report-tenant-'.uniqid(),
        ]);

        app(RestaurantSettingsService::class)->save($tenant, [
            'currency_symbol' => '£',
            'table_count' => 1,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);
        $cashier = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reports',
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Report Item',
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $admin, $waiter, $cashier, $item];
    }

    private function createOrder(Tenant $tenant, User $waiter, MenuItem $item, array $overrides = []): Order
    {
        $createdAt = Carbon::parse($overrides['created_at'] ?? now());

        $order = app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => $overrides['source_type'] ?? OrderSourceTypes::TAKEAWAY,
            'table_number' => $overrides['table_number'] ?? null,
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'quantity' => $overrides['quantity'] ?? 1,
                ],
            ],
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $order->items()->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh(['items', 'kitchenTicket']);
    }
}
