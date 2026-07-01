<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarCloudPrntBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_printer_can_fetch_pending_jobs_with_token(): void
    {
        [$printer, $job, $token] = $this->bridgeFixture();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/printer-bridge/jobs')
            ->assertOk()
            ->assertJsonPath('printer_id', $printer->id)
            ->assertJsonPath('has_jobs', true)
            ->assertJsonPath('jobs.0.id', $job->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/printer-bridge/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('payload.order_number', 'ORD-BRIDGE')
            ->assertJsonStructure(['escpos_base64', 'plain_text']);
    }

    public function test_invalid_token_blocked(): void
    {
        $this->bridgeFixture();

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/printer-bridge/jobs')
            ->assertUnauthorized();
    }

    public function test_job_can_be_marked_printed_and_failed(): void
    {
        [, $printedJob, $token] = $this->bridgeFixture();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/printer-bridge/jobs/{$printedJob->id}/printed")
            ->assertOk()
            ->assertJsonPath('status', PrintJob::STATUS_PRINTED);

        $this->assertDatabaseHas('print_jobs', [
            'id' => $printedJob->id,
            'status' => PrintJob::STATUS_PRINTED,
            'last_error' => null,
        ]);

        [, $failedJob] = $this->bridgeFixture('bridge-token-2');

        $this->withHeader('Authorization', 'Bearer bridge-token-2')
            ->postJson("/api/printer-bridge/jobs/{$failedJob->id}/failed", [
                'error' => 'Bridge printer offline',
            ])
            ->assertOk()
            ->assertJsonPath('status', PrintJob::STATUS_FAILED);

        $this->assertDatabaseHas('print_jobs', [
            'id' => $failedJob->id,
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => 'Bridge printer offline',
        ]);
    }

    public function test_bridge_spec_document_created(): void
    {
        $path = base_path('docs/local-printer-bridge.md');

        $this->assertFileExists($path);
        $this->assertStringContainsString('/api/printer-bridge/jobs', file_get_contents($path));
        $this->assertStringContainsString('Authorization: Bearer', file_get_contents($path));
        $this->assertStringContainsString('CloudPRNT', file_get_contents($path));
    }

    private function bridgeFixture(string $token = 'bridge-token-1'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Bridge Tenant '.uniqid(),
            'slug' => 'bridge-tenant-'.uniqid(),
        ]);
        $printer = Printer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Star Bridge Printer',
            'type' => Printer::TYPE_STAR_CLOUDPRNT,
            'role' => Printer::ROLE_RECEIPT,
            'paper_size' => '80mm',
            'bridge_token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);
        $job = PrintJob::query()->create([
            'tenant_id' => $tenant->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'status' => PrintJob::STATUS_QUEUED,
            'payload' => [
                'paper_size' => '80mm',
                'order_number' => 'ORD-BRIDGE',
                'total_payable' => 12,
                'total_paid' => 12,
                'items' => [
                    ['name' => 'Bridge Item', 'quantity' => 1, 'line_total' => 12],
                ],
            ],
            'available_at' => now(),
        ]);

        return [$printer, $job, $token];
    }
}
