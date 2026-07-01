<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Category;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Backups\BackupService;
use App\Services\Billing\InvoiceService;
use App\Support\ChatRooms;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PosLabEndToEndHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_poslab_end_to_end_happy_path(): void
    {
        Storage::fake('backups');
        Storage::fake('local');
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        PlanFeature::query()->updateOrCreate(
            ['plan_id' => $plan->id, 'feature_key' => BackupService::featureKey()],
            ['name' => 'Backups', 'enabled' => true]
        );

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::ADMIN]);
        $waiter = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::WAITER]);
        $kitchen = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::KITCHEN]);
        $counter = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::COUNTER]);

        Printer::factory()->kitchen()->create(['tenant_id' => $tenant->id, 'name' => 'Kitchen Printer']);
        Printer::factory()->receipt()->create(['tenant_id' => $tenant->id, 'name' => 'Receipt Printer']);

        $this->actingAs($admin)
            ->put('/tenant/settings/restaurant', [
                'restaurant_name' => 'QA Restaurant',
                'logo_text' => 'QA',
                'address' => '1 Test Street',
                'phone' => '01234567890',
                'email' => 'qa@example.test',
                'website' => 'https://example.test',
                'currency_symbol' => 'GBP ',
                'currency_code' => 'GBP',
                'service_charge_percent' => 0,
                'tax_vat_percent' => 0,
                'table_count' => 2,
                'invoice_footer' => 'Thank you for testing.',
                'theme_color' => '#0f172a',
            ])
            ->assertRedirect('/tenant/settings/restaurant');

        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($waiter)->get('/tenant/admin')->assertForbidden();
        $this->actingAs($kitchen)->get('/counter')->assertForbidden();

        $otherTenant = Tenant::factory()->create();
        $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Other Tenant Category']);
        MenuItem::factory()->forTenantCategory($otherTenant, $otherCategory)->create(['name' => 'Other Tenant Item']);

        $this->actingAs($admin)
            ->post('/tenant/menu/categories', [
                'name' => 'Mains',
                'description' => 'Dinner menu',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect('/tenant/menu/categories');

        $category = Category::query()->where('tenant_id', $tenant->id)->where('name', 'Mains')->firstOrFail();

        $this->actingAs($admin)
            ->post('/tenant/menu/items', [
                'category_id' => $category->id,
                'name' => 'QA Burger',
                'description' => 'End-to-end test item',
                'price' => '10.00',
                'is_available' => '1',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect('/tenant/menu/items');

        $item = MenuItem::query()->where('tenant_id', $tenant->id)->where('name', 'QA Burger')->firstOrFail();

        $this->actingAs($admin)
            ->put("/tenant/menu/items/{$item->id}", [
                'category_id' => $category->id,
                'name' => 'QA Burger Updated',
                'description' => 'Updated end-to-end test item',
                'price' => '10.00',
                'is_available' => '1',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect('/tenant/menu/items');

        $item->refresh();

        $this->actingAs($waiter)
            ->getJson('/waiter/pos/data')
            ->assertOk()
            ->assertJsonFragment(['name' => 'QA Burger Updated'])
            ->assertJsonMissing(['name' => 'Other Tenant Item']);

        $baseOrderId = $this->actingAs($waiter)
            ->postJson('/waiter/pos/orders', [
                'source_type' => OrderSourceTypes::TABLE,
                'table_number' => 1,
                'kitchen_note' => 'No onions',
                'items' => [
                    ['menu_item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('order.source_type', OrderSourceTypes::TABLE)
            ->assertJsonPath('order.total', '20.00')
            ->json('order.id');

        $addonOrderId = $this->actingAs($waiter)
            ->postJson('/waiter/pos/orders/addon', [
                'source_type' => OrderSourceTypes::TABLE,
                'table_number' => 1,
                'items' => [
                    ['menu_item_id' => $item->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('order.is_addon', true)
            ->json('order.id');

        $this->assertNotSame($baseOrderId, $addonOrderId);
        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'type' => PrintJob::TYPE_KITCHEN_TICKET,
            'status' => PrintJob::STATUS_QUEUED,
        ]);

        $pendingTickets = $this->actingAs($kitchen)
            ->getJson('/kitchen/data')
            ->assertOk()
            ->json('columns.pending');

        $this->assertSame([$baseOrderId, $addonOrderId], collect($pendingTickets)->pluck('order.id')->all());

        $baseTicket = KitchenTicket::query()->where('order_id', $baseOrderId)->firstOrFail();

        $this->actingAs($kitchen)
            ->patchJson("/kitchen/tickets/{$baseTicket->id}/status", ['status' => KitchenTicket::STATUS_COOKING])
            ->assertOk()
            ->assertJsonPath('ticket.status', KitchenTicket::STATUS_COOKING);

        $this->actingAs($kitchen)
            ->patchJson("/kitchen/tickets/{$baseTicket->id}/status", ['status' => KitchenTicket::STATUS_READY])
            ->assertOk()
            ->assertJsonPath('ticket.status', KitchenTicket::STATUS_READY);

        $this->actingAs($counter)
            ->getJson('/counter/data')
            ->assertOk()
            ->assertJsonFragment(['id' => $baseOrderId])
            ->assertJsonFragment(['id' => $addonOrderId]);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$baseOrderId}/discount", [
                'type' => 'flat',
                'value' => 5,
                'reason' => 'QA discount',
            ])
            ->assertOk()
            ->assertJsonPath('bill.subtotal', 30)
            ->assertJsonPath('bill.discount_total', 5)
            ->assertJsonPath('bill.total_payable', 25);

        $this->actingAs($counter)
            ->postJson("/counter/bills/{$baseOrderId}/split", ['people_count' => 2])
            ->assertCreated()
            ->assertJsonPath('split_bill.amount_per_person', 12.5);

        $paymentId = $this->actingAs($counter)
            ->postJson("/counter/bills/{$baseOrderId}/payment", [
                'cash_amount' => 10,
                'card_amount' => 15,
                'manual_reference' => 'QA-CARD-1',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.status', Order::PAYMENT_PAID)
            ->assertJsonPath('payment.cash_amount', 10)
            ->assertJsonPath('payment.card_amount', 15)
            ->json('payment.id');

        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payment_id' => $paymentId,
            'status' => PrintJob::STATUS_QUEUED,
        ]);

        $invoicePath = app(InvoiceService::class)->generate(
            $tenant,
            Order::query()->findOrFail($baseOrderId),
            Payment::query()->findOrFail($paymentId)
        );
        Storage::disk('local')->assertExists($invoicePath);

        $this->actingAs($waiter)
            ->postJson('/tenant/chat/rooms/'.ChatRooms::WAITER_COUNTER.'/messages', [
                'message' => 'Table 1 paid in QA flow.',
                'related_order_id' => $baseOrderId,
            ])
            ->assertCreated()
            ->assertJsonPath('message.message', 'Table 1 paid in QA flow.');

        $this->actingAs($admin)
            ->post('/tenant/backups', ['type' => Backup::TYPE_DATABASE])
            ->assertRedirect();

        $backup = Backup::query()->where('tenant_id', $tenant->id)->firstOrFail();
        Storage::disk('backups')->assertExists($backup->path);

        $outsideAdmin = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'role' => Roles::ADMIN,
        ]);

        $this->actingAs($outsideAdmin)
            ->get("/tenant/backups/{$backup->id}/download")
            ->assertForbidden();
    }
}
