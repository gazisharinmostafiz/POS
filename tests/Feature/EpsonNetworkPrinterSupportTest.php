<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Tenant;
use App\Services\Printing\EscPosContentGenerator;
use App\Services\Printing\PrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpsonNetworkPrinterSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_escpos_receipt_content(): void
    {
        $job = new PrintJob([
            'type' => PrintJob::TYPE_RECEIPT,
            'payload' => [
                'paper_size' => '80mm',
                'order_number' => 'ORD-100',
                'cash_amount' => 20,
                'card_amount' => 0,
                'total_paid' => 20,
                'total_payable' => 18,
                'change' => 2,
                'items' => [
                    ['name' => 'Fish Curry', 'quantity' => 1, 'line_total' => 18],
                ],
            ],
        ]);

        $content = app(EscPosContentGenerator::class)->receipt($job);

        $this->assertStringStartsWith("\x1B@", $content);
        $this->assertStringContainsString('Receipt', $content);
        $this->assertStringContainsString('Order: ORD-100', $content);
        $this->assertStringContainsString('1 x Fish Curry', $content);
        $this->assertStringContainsString('Total', $content);
        $this->assertStringContainsString("\x1DVA\x00", $content);
    }

    public function test_generate_kitchen_ticket_content(): void
    {
        $job = new PrintJob([
            'type' => PrintJob::TYPE_KITCHEN_TICKET,
            'payload' => [
                'paper_size' => '80mm',
                'ticket_number' => 'KIT-100',
                'order_number' => 'ORD-100',
                'source_type' => 'table',
                'table_number' => 4,
                'is_addon' => true,
                'waiter' => 'Waiter One',
                'kitchen_note' => 'No chilli',
                'items' => [
                    ['name' => 'Noodles', 'quantity' => 2, 'note' => 'No egg'],
                ],
            ],
        ]);

        $content = app(EscPosContentGenerator::class)->kitchenTicket($job);

        $this->assertStringStartsWith("\x1B@", $content);
        $this->assertStringContainsString('KITCHEN', $content);
        $this->assertStringContainsString('Ticket: KIT-100', $content);
        $this->assertStringContainsString('TABLE 4 ADD-ON', $content);
        $this->assertStringContainsString('2 x Noodles', $content);
        $this->assertStringContainsString('No chilli', $content);
    }

    public function test_mocked_printer_success_marks_job_printed(): void
    {
        [$tenant, $printer, $job] = $this->eposJob();

        Http::fake([
            'http://192.168.10.50/*' => Http::response('<response success="true"/>', 200),
        ]);

        app(PrintService::class)->process($job);

        $job->refresh();

        $this->assertSame(PrintJob::STATUS_PRINTED, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertNotNull($job->printed_at);
        $this->assertNull($job->last_error);
        $this->assertSame($tenant->id, $job->tenant_id);
        $this->assertSame($printer->id, $job->printer_id);
    }

    public function test_mocked_printer_failure_marks_job_failed(): void
    {
        [, , $job] = $this->eposJob();

        Http::fake([
            'http://192.168.10.50/*' => Http::response('offline', 500),
        ]);

        app(PrintService::class)->process($job);

        $job->refresh();

        $this->assertSame(PrintJob::STATUS_FAILED, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertNotNull($job->failed_at);
        $this->assertStringContainsString('HTTP 500', $job->last_error);

        $job->retry();
        $this->assertSame(PrintJob::STATUS_QUEUED, $job->status);
        $this->assertNull($job->failed_at);
        $this->assertNull($job->last_error);
    }

    private function eposJob(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Epson Tenant '.uniqid(),
            'slug' => 'epson-tenant-'.uniqid(),
        ]);
        $printer = Printer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Kitchen Epson',
            'type' => Printer::TYPE_EPSON_EPOS,
            'role' => Printer::ROLE_KITCHEN,
            'ip_address' => '192.168.10.50',
            'port' => 80,
            'paper_size' => '80mm',
            'connection_settings' => [
                'ip_address' => '192.168.10.50',
                'port' => 80,
                'device_id' => 'local_printer',
            ],
            'is_active' => true,
        ]);
        $job = PrintJob::query()->create([
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_KITCHEN_TICKET,
            'status' => PrintJob::STATUS_QUEUED,
            'payload' => [
                'paper_size' => '80mm',
                'ticket_number' => 'KIT-EPOS',
                'order_number' => 'ORD-EPOS',
                'source_type' => 'takeaway',
                'items' => [
                    ['name' => 'Soup', 'quantity' => 1],
                ],
            ],
            'available_at' => now(),
        ]);

        return [$tenant, $printer, $job];
    }
}
