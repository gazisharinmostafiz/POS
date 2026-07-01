<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Orders\OrderService;
use App\Support\OrderSourceTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrinterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_printer(): void
    {
        [$tenant, $admin] = $this->printerFixture();

        $this->actingAs($admin)
            ->post('/tenant/printers', [
                'name' => 'Front Receipt',
                'type' => Printer::TYPE_BROWSER,
                'role' => Printer::ROLE_RECEIPT,
                'ip_address' => null,
                'port' => null,
                'paper_size' => '80mm',
                'is_active' => '1',
            ])
            ->assertRedirect('/tenant/printers');

        $this->assertDatabaseHas('printers', [
            'tenant_id' => $tenant->id,
            'name' => 'Front Receipt',
            'type' => Printer::TYPE_BROWSER,
            'role' => Printer::ROLE_RECEIPT,
            'paper_size' => '80mm',
            'is_active' => true,
        ]);
    }

    public function test_test_print_job_created(): void
    {
        [$tenant, $admin] = $this->printerFixture();
        $printer = $this->printer($tenant, Printer::ROLE_RECEIPT);

        $this->actingAs($admin)
            ->post("/tenant/printers/{$printer->id}/test")
            ->assertRedirect('/tenant/printers');

        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_TEST,
            'status' => PrintJob::STATUS_QUEUED,
        ]);
    }

    public function test_receipt_print_job_created(): void
    {
        [$tenant, , $cashier, $waiter, $item] = $this->printerFixture();
        $printer = $this->printer($tenant, Printer::ROLE_RECEIPT);
        $order = $this->order($tenant, $waiter, $item);

        $payment = app(BillingService::class)->recordPayment($tenant, $order, $cashier, 12, 0);

        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'status' => PrintJob::STATUS_QUEUED,
        ]);
    }

    public function test_kitchen_ticket_print_job_created(): void
    {
        [$tenant, , , $waiter, $item] = $this->printerFixture();
        $printer = $this->printer($tenant, Printer::ROLE_KITCHEN);

        $order = $this->order($tenant, $waiter, $item);

        $this->assertDatabaseHas('print_jobs', [
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'kitchen_ticket_id' => $order->kitchenTicket->id,
            'type' => PrintJob::TYPE_KITCHEN_TICKET,
            'status' => PrintJob::STATUS_QUEUED,
        ]);

        $job = PrintJob::query()->where('type', PrintJob::TYPE_KITCHEN_TICKET)->firstOrFail();
        $this->assertSame(false, $job->payload['category_routing']['enabled']);
    }

    public function test_retry_failed_job_works(): void
    {
        [$tenant, $admin] = $this->printerFixture();
        $printer = $this->printer($tenant, Printer::ROLE_RECEIPT);
        $job = PrintJob::query()->create([
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_TEST,
            'status' => PrintJob::STATUS_FAILED,
            'payload' => ['message' => 'failed test'],
            'attempts' => 2,
            'failed_at' => now(),
            'last_error' => 'Printer offline',
        ]);

        $this->actingAs($admin)
            ->post("/tenant/printers/jobs/{$job->id}/retry")
            ->assertRedirect('/tenant/printers');

        $job->refresh();

        $this->assertSame(PrintJob::STATUS_QUEUED, $job->status);
        $this->assertNull($job->failed_at);
        $this->assertNull($job->last_error);
        $this->assertNotNull($job->available_at);
    }

    private function printerFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Printer Tenant '.uniqid(),
            'slug' => 'printer-tenant-'.uniqid(),
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);
        $cashier = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
        ]);
        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Printer Category',
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Printer Item',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
        ]);

        return [$tenant, $admin, $cashier, $waiter, $item];
    }

    private function printer(Tenant $tenant, string $role): Printer
    {
        return Printer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst($role).' Printer',
            'type' => Printer::TYPE_BROWSER,
            'role' => $role,
            'paper_size' => '80mm',
            'is_active' => true,
        ]);
    }

    private function order(Tenant $tenant, User $waiter, MenuItem $item): Order
    {
        return app(OrderService::class)->createOrder($tenant, $waiter, [
            'source_type' => OrderSourceTypes::TAKEAWAY,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);
    }
}
