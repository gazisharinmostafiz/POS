<?php

namespace App\Services\Printing;

use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Tenant;
use App\Services\Printing\Adapters\EpsonEposAdapter;
use App\Services\Printing\Adapters\NetworkPrinterAdapter;
use App\Services\Printing\Contracts\PrinterAdapterInterface;

class PrintService
{
    public function createTestJob(Printer $printer): PrintJob
    {
        return $this->createJob($printer, PrintJob::TYPE_TEST, [
            'title' => 'Test print',
            'printer' => $printer->name,
            'type' => $printer->type,
            'paper_size' => $printer->paper_size,
            'message' => 'Tong POS printer test',
        ]);
    }

    public function queueKitchenTicket(KitchenTicket $ticket): void
    {
        $ticket->loadMissing('order.items.menuItem.category', 'order.waiter', 'tenant');
        $order = $ticket->order;

        foreach ($this->printers($ticket->tenant, $ticket->branch, Printer::ROLE_KITCHEN) as $printer) {
            $this->createJob($printer, PrintJob::TYPE_KITCHEN_TICKET, [
                'ticket_number' => $ticket->ticket_number,
                'order_number' => $order->order_number,
                'source_type' => $order->source_type,
                'table_number' => $order->table_number,
                'is_addon' => $order->is_addon,
                'waiter' => $order->waiter?->name,
                'kitchen_note' => $ticket->kitchen_note,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->item_name_snapshot,
                    'quantity' => $item->quantity,
                    'note' => $item->item_note,
                    'category' => $item->menuItem?->category?->name,
                ])->values()->all(),
                'category_routing' => [
                    'enabled' => false,
                    'placeholder' => 'Kitchen category routing will target printers from printer.kitchen_category_routes later.',
                ],
            ], [
                'order_id' => $order->id,
                'kitchen_ticket_id' => $ticket->id,
            ]);
        }
    }

    public function queueReceipt(Payment $payment): void
    {
        $payment->loadMissing('order.items', 'tenant');
        $order = $payment->order;

        foreach ($this->printers($payment->tenant, $payment->branch, Printer::ROLE_RECEIPT) as $printer) {
            $this->createJob($printer, PrintJob::TYPE_RECEIPT, [
                'order_number' => $order?->order_number,
                'order_ids' => $payment->order_ids,
                'cash_amount' => (float) $payment->cash_amount,
                'card_amount' => (float) $payment->card_amount,
                'total_paid' => (float) $payment->total_paid,
                'total_payable' => (float) $payment->total_payable,
                'change' => (float) $payment->change_amount,
                'status' => $payment->status,
                'items' => $order?->items->map(fn ($item) => [
                    'name' => $item->item_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price_snapshot,
                    'line_total' => (float) $item->line_total,
                ])->values()->all() ?? [],
            ], [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    public function retry(PrintJob $job): PrintJob
    {
        return $job->retry();
    }

    public function process(PrintJob $job): PrintJob
    {
        $job->loadMissing('printer');
        $job->increment('attempts');

        try {
            $this->adapterFor($job->printer)->print($job->fresh('printer'));

            $job->forceFill([
                'status' => PrintJob::STATUS_PRINTED,
                'printed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $job->forceFill([
                'status' => PrintJob::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return $job->fresh('printer');
    }

    public function testConnection(Printer $printer): bool
    {
        return $this->adapterFor($printer)->testConnection($printer);
    }

    private function createJob(Printer $printer, string $type, array $payload, array $attributes = []): PrintJob
    {
        return PrintJob::query()->create(array_merge([
            'tenant_id' => $printer->tenant_id,
            'branch_id' => $printer->branch_id,
            'printer_id' => $printer->id,
            'type' => $type,
            'status' => PrintJob::STATUS_QUEUED,
            'payload' => array_merge([
                'printer_type' => $printer->type,
                'delivery' => $printer->type === Printer::TYPE_BROWSER ? 'browser' : 'adapter_pending',
            ], $payload),
            'available_at' => now(),
        ], $attributes));
    }

    private function printers(Tenant $tenant, $branch, string $role)
    {
        return Printer::query()
            ->forTenant($tenant)
            ->forBranch($branch)
            ->forRole($role)
            ->where('is_active', true)
            ->orderByRaw('branch_id is null')
            ->orderBy('name')
            ->get();
    }

    private function adapterFor(Printer $printer): PrinterAdapterInterface
    {
        return match ($printer->type) {
            Printer::TYPE_NETWORK => app(NetworkPrinterAdapter::class),
            Printer::TYPE_EPSON_EPOS => app(EpsonEposAdapter::class),
            default => throw new \RuntimeException("Printer adapter [{$printer->type}] is not available for server-side printing."),
        };
    }
}
