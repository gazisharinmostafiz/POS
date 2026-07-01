<?php

namespace Tests\Feature;

use App\Events\KitchenTicketCreated;
use App\Events\KitchenTicketStatusChanged;
use App\Events\OrderCreated;
use App\Events\PaymentCompleted;
use App\Events\TableStatusChanged;
use App\Jobs\LaunchReadinessProbeJob;
use App\Models\Addon;
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
use App\Models\ProviderAccountSetting;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Services\Backups\BackupService;
use App\Services\Billing\InvoiceService;
use App\Services\FeatureGate;
use App\Services\Migrations\ServerMigrationService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Releases\HealthCheckService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_role_access_and_tenant_isolation_launch_gate(): void
    {
        [$tenant, $users] = $this->launchFixture();
        $otherTenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);
        MenuItem::factory()->forTenantCategory($otherTenant, $otherCategory)->create(['name' => 'Other Tenant Soup']);

        $this->actingAs($users['platform_owner'])->get('/platform')->assertOk();
        $this->actingAs($users['admin'])->get('/platform')->assertForbidden();
        $this->actingAs($users['admin'])->get('/tenant/admin')->assertOk();
        $this->actingAs($users['waiter'])->get('/waiter/pos')->assertOk();
        $this->actingAs($users['waiter'])->get('/counter')->assertForbidden();
        $this->actingAs($users['counter'])->get('/counter')->assertOk();
        $this->actingAs($users['counter'])->get('/kitchen')->assertForbidden();
        $this->actingAs($users['kitchen'])->get('/kitchen')->assertOk();
        $this->actingAs($users['kitchen'])->get('/tenant/admin')->assertForbidden();

        $this->actingAs($users['waiter'])
            ->getJson('/waiter/pos/data')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Other Tenant Soup'])
            ->assertJsonFragment(['currency_symbol' => 'GBP']);

        $this->assertSame($tenant->id, $users['admin']->tenant_id);
    }

    public function test_waiter_to_kitchen_to_counter_invoice_print_and_addon_fifo_launch_gate(): void
    {
        Storage::fake('local');
        [$tenant, $users, $item] = $this->launchFixtureWithMenuAndPrinters();

        Carbon::setTestNow('2026-07-01 10:00:00');
        $tableOneOrder = $this->actingAs($users['waiter'])
            ->postJson('/waiter/pos/orders', [
                'source_type' => OrderSourceTypes::TABLE,
                'table_number' => 1,
                'kitchen_note' => 'Launch flow',
                'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('order.id');

        Carbon::setTestNow('2026-07-01 10:05:00');
        $tableTwoOrder = $this->actingAs($users['waiter'])
            ->postJson('/waiter/pos/orders', [
                'source_type' => OrderSourceTypes::TABLE,
                'table_number' => 2,
                'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('order.id');

        Carbon::setTestNow('2026-07-01 10:10:00');
        $addonOrder = $this->actingAs($users['waiter'])
            ->postJson('/waiter/pos/orders/addon', [
                'source_type' => OrderSourceTypes::TABLE,
                'table_number' => 1,
                'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('order.is_addon', true)
            ->json('order.id');
        Carbon::setTestNow();

        $pending = $this->actingAs($users['kitchen'])
            ->getJson('/kitchen/data')
            ->assertOk()
            ->json('columns.pending');

        $this->assertSame([$tableOneOrder, $tableTwoOrder, $addonOrder], collect($pending)->pluck('order.id')->all());
        $this->assertTrue(collect($pending)->firstWhere('order.id', $addonOrder)['is_addon']);

        $ticket = KitchenTicket::query()->where('order_id', $tableOneOrder)->firstOrFail();
        $this->actingAs($users['kitchen'])
            ->patchJson("/kitchen/tickets/{$ticket->id}/status", ['status' => KitchenTicket::STATUS_COOKING])
            ->assertOk();
        $this->actingAs($users['kitchen'])
            ->patchJson("/kitchen/tickets/{$ticket->id}/status", ['status' => KitchenTicket::STATUS_READY])
            ->assertOk();

        $this->actingAs($users['counter'])
            ->postJson("/counter/bills/{$tableOneOrder}/payment", [
                'cash_amount' => 20,
                'card_amount' => 40,
                'manual_reference' => 'LAUNCH-MIXED-1',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.status', Order::PAYMENT_PAID)
            ->assertJsonPath('payment.cash_amount', 20)
            ->assertJsonPath('payment.card_amount', 40);

        $payment = Payment::query()->where('tenant_id', $tenant->id)->latest()->firstOrFail();
        $invoice = app(InvoiceService::class);
        $textPath = $invoice->generate($tenant, Order::query()->findOrFail($tableOneOrder), $payment);
        $pdfPath = $invoice->generatePdf($tenant, Order::query()->findOrFail($tableOneOrder), $payment);

        Storage::disk('local')->assertExists($textPath);
        Storage::disk('local')->assertExists($pdfPath);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($pdfPath));

        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'type' => PrintJob::TYPE_KITCHEN_TICKET,
            'status' => PrintJob::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payment_id' => $payment->id,
            'status' => PrintJob::STATUS_QUEUED,
        ]);
    }

    public function test_subscription_status_and_addon_feature_restrictions_launch_gate(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
        $tenant = Tenant::factory()->create([
            'plan_id' => $starter->id,
            'subscription_status' => 'active',
        ]);

        $gate = app(FeatureGate::class);
        $this->assertFalse($gate->tenantHasFeature($tenant, 'reports.advanced'));

        TenantAddon::query()->create([
            'tenant_id' => $tenant->id,
            'addon_id' => Addon::query()->where('slug', 'advanced-reports')->value('id'),
            'status' => TenantAddon::STATUS_ACTIVE,
        ]);

        $this->assertTrue($gate->tenantHasFeature($tenant->fresh(), 'reports.advanced'));

        $tenant->forceFill([
            'subscription_status' => 'past_due',
            'subscription_ends_at' => now()->subMinute(),
        ])->save();

        $waiter = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::WAITER]);
        $this->actingAs($waiter)->get('/waiter/pos')->assertStatus(402);
    }

    public function test_payment_provider_printer_backup_restore_and_migration_dry_run_launch_gate(): void
    {
        Storage::fake('backups');
        [$tenant, $users, $item] = $this->launchFixtureWithMenuAndPrinters();
        $order = app(OrderService::class)->createOrder($tenant, $users['waiter'], [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        ProviderAccountSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'teya',
            'account_reference' => 'launch-sandbox',
            'terminal_id' => 'TERM-LAUNCH',
            'encrypted_credentials' => [
                'base_url' => 'https://teya.test',
                'api_key' => 'sandbox-key',
            ],
            'is_active' => true,
        ]);

        Http::fake([
            'https://teya.test/payments' => Http::response([
                'id' => 'teya_launch_1',
                'status' => 'approved',
            ], 200),
        ]);

        $providerResult = app(PaymentService::class)->charge(
            $tenant,
            $order,
            $users['counter'],
            'teya',
            20,
            metadata: ['currency' => 'GBP', 'card_number' => '4111111111111111']
        );

        $this->assertSame(Order::PAYMENT_PAID, $providerResult['status']);
        $this->assertSame('teya_launch_1', $providerResult['provider_transaction_id']);
        $this->assertArrayNotHasKey('card_number', $providerResult['metadata']);
        $this->assertDatabaseHas('payment_provider_logs', [
            'tenant_id' => $tenant->id,
            'provider' => 'teya',
            'success' => true,
        ]);

        $printer = Printer::query()->where('tenant_id', $tenant->id)->where('role', Printer::ROLE_RECEIPT)->firstOrFail();
        $this->actingAs($users['admin'])
            ->post("/tenant/printers/{$printer->id}/test")
            ->assertRedirect();
        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_TEST,
        ]);

        $backup = app(BackupService::class)->createTenantBackup($tenant, Backup::TYPE_DATABASE, $users['admin']);
        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status);
        Storage::disk('backups')->assertExists($backup->path);
        $this->assertNotEmpty($backup->checksum);

        $this->actingAs($users['admin'])
            ->post("/tenant/backups/{$backup->id}/restore")
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'backup_restored',
            'auditable_id' => $backup->id,
        ]);

        $migration = app(ServerMigrationService::class)->generatePackage($users['platform_owner']);
        $this->assertSame('completed', $migration->status);
        $this->assertNotEmpty($migration->checksum);
        Storage::disk('backups')->assertExists($migration->path);
    }

    public function test_websocket_queue_scheduler_and_monitoring_launch_gate(): void
    {
        [$tenant, $users, $item] = $this->launchFixtureWithMenuAndPrinters();

        Event::fake([
            OrderCreated::class,
            KitchenTicketCreated::class,
            KitchenTicketStatusChanged::class,
            PaymentCompleted::class,
            TableStatusChanged::class,
        ]);

        $order = app(OrderService::class)->createOrder($tenant, $users['waiter'], [
            'source_type' => OrderSourceTypes::TABLE,
            'table_number' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->broadcastOn()[0]->name === 'private-tenant.'.$tenant->id);
        Event::assertDispatched(KitchenTicketCreated::class);
        Event::assertDispatched(TableStatusChanged::class);

        Queue::fake();
        LaunchReadinessProbeJob::dispatch('launch-readiness-probe');
        Queue::assertPushed(LaunchReadinessProbeJob::class);

        (new LaunchReadinessProbeJob('launch-readiness-probe-handle'))->handle();
        $this->assertSame('processed', Cache::get('launch-readiness-probe-handle'));

        $schedule = app(Schedule::class);
        $method = new ReflectionMethod(app(\App\Console\Kernel::class), 'schedule');
        $method->setAccessible(true);
        $method->invoke(app(\App\Console\Kernel::class), $schedule);
        $this->assertTrue(collect($schedule->events())->contains(fn ($event) => str_contains($event->command ?? '', 'backups:run')));

        config(['monitoring.error_tracking_dsn' => 'https://example.invalid/1']);
        $report = app(HealthCheckService::class)->report();
        $this->assertSame('ok', $report['status']);
        $this->assertTrue($report['checks']['monitoring']['error_tracking_configured']);

        $this->assertSame($tenant->id, $order->tenant_id);
    }

    private function launchFixtureWithMenuAndPrinters(): array
    {
        [$tenant, $users] = $this->launchFixture();

        Printer::factory()->kitchen()->create(['tenant_id' => $tenant->id, 'name' => 'Launch Kitchen']);
        Printer::factory()->receipt()->create(['tenant_id' => $tenant->id, 'name' => 'Launch Receipt']);

        $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Launch Mains']);
        $item = MenuItem::factory()->forTenantCategory($tenant, $category)->create([
            'name' => 'Launch Burger',
            'price' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $users, $item];
    }

    private function launchFixture(): array
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        PlanFeature::query()->updateOrCreate(
            ['plan_id' => $plan->id, 'feature_key' => BackupService::featureKey()],
            ['name' => 'Backups', 'enabled' => true]
        );

        $tenant = Tenant::factory()->create([
            'name' => 'Launch Tenant',
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        $users = [
            'platform_owner' => User::factory()->create(['role' => Roles::PLATFORM_OWNER, 'tenant_id' => null]),
            'admin' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::ADMIN]),
            'waiter' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::WAITER]),
            'counter' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::COUNTER]),
            'kitchen' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => Roles::KITCHEN]),
        ];

        $this->actingAs($users['admin'])
            ->put('/tenant/settings/restaurant', [
                'restaurant_name' => 'Launch Restaurant',
                'logo_text' => 'LR',
                'address' => '1 Launch Street',
                'phone' => '01234567890',
                'email' => 'launch@example.test',
                'website' => 'https://example.test',
                'currency_symbol' => 'GBP ',
                'currency_code' => 'GBP',
                'service_charge_percent' => 0,
                'tax_vat_percent' => 0,
                'table_count' => 3,
                'invoice_footer' => 'Launch receipt footer.',
                'theme_color' => '#0f172a',
            ])
            ->assertRedirect('/tenant/settings/restaurant');

        return [$tenant->fresh(), $users];
    }
}
