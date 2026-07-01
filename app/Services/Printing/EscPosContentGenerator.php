<?php

namespace App\Services\Printing;

use App\Models\PrintJob;

class EscPosContentGenerator
{
    public function generate(PrintJob $job): string
    {
        return match ($job->type) {
            PrintJob::TYPE_RECEIPT => $this->receipt($job),
            PrintJob::TYPE_KITCHEN_TICKET => $this->kitchenTicket($job),
            default => $this->test($job),
        };
    }

    public function receipt(PrintJob $job): string
    {
        $payload = $job->payload;
        $width = $this->width($payload['paper_size'] ?? '80mm');
        $lines = [
            $this->center('Tong POS', $width),
            $this->center('Receipt', $width),
            str_repeat('-', $width),
            'Order: '.($payload['order_number'] ?? 'N/A'),
            str_repeat('-', $width),
        ];

        foreach ($payload['items'] ?? [] as $item) {
            $name = (string) ($item['name'] ?? 'Item');
            $qty = (int) ($item['quantity'] ?? 1);
            $lineTotal = number_format((float) ($item['line_total'] ?? 0), 2);
            $lines[] = $this->columns($qty.' x '.$name, $lineTotal, $width);
        }

        $lines = array_merge($lines, [
            str_repeat('-', $width),
            $this->columns('Cash', number_format((float) ($payload['cash_amount'] ?? 0), 2), $width),
            $this->columns('Card', number_format((float) ($payload['card_amount'] ?? 0), 2), $width),
            $this->columns('Total', number_format((float) ($payload['total_payable'] ?? 0), 2), $width),
            $this->columns('Paid', number_format((float) ($payload['total_paid'] ?? 0), 2), $width),
            $this->columns('Change', number_format((float) ($payload['change'] ?? 0), 2), $width),
            str_repeat('-', $width),
            $this->center('Thank you', $width),
        ]);

        return $this->escpos($lines);
    }

    public function kitchenTicket(PrintJob $job): string
    {
        $payload = $job->payload;
        $width = $this->width($payload['paper_size'] ?? '80mm');
        $source = strtoupper((string) ($payload['source_type'] ?? 'order'));
        $table = ! empty($payload['table_number']) ? ' TABLE '.$payload['table_number'] : '';
        $addon = ! empty($payload['is_addon']) ? ' ADD-ON' : '';
        $lines = [
            $this->center('KITCHEN', $width),
            str_repeat('=', $width),
            'Ticket: '.($payload['ticket_number'] ?? 'N/A'),
            'Order: '.($payload['order_number'] ?? 'N/A'),
            trim($source.$table.$addon),
            'Waiter: '.($payload['waiter'] ?? 'N/A'),
            str_repeat('-', $width),
        ];

        foreach ($payload['items'] ?? [] as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $lines[] = $qty.' x '.($item['name'] ?? 'Item');

            if (! empty($item['note'])) {
                $lines[] = '  Note: '.$item['note'];
            }
        }

        if (! empty($payload['kitchen_note'])) {
            $lines[] = str_repeat('-', $width);
            $lines[] = 'Kitchen note:';
            $lines[] = $payload['kitchen_note'];
        }

        $lines[] = str_repeat('=', $width);

        return $this->escpos($lines);
    }

    public function test(PrintJob $job): string
    {
        $payload = $job->payload;
        $width = $this->width($payload['paper_size'] ?? '80mm');

        return $this->escpos([
            $this->center($payload['title'] ?? 'Test print', $width),
            str_repeat('-', $width),
            'Printer: '.($payload['printer'] ?? 'Unknown'),
            'Type: '.($payload['type'] ?? 'Unknown'),
            'Paper: '.($payload['paper_size'] ?? '80mm'),
            $payload['message'] ?? 'Printer test',
        ]);
    }

    public function plainText(PrintJob $job): string
    {
        return preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $this->generate($job));
    }

    private function escpos(array $lines): string
    {
        return "\x1B@".implode("\n", $this->wrapLines($lines))."\n\n\n\x1DVA\x00";
    }

    private function wrapLines(array $lines): array
    {
        return collect($lines)
            ->flatMap(fn ($line) => explode("\n", wordwrap((string) $line, 48, "\n", true)))
            ->all();
    }

    private function width(string $paperSize): int
    {
        return $paperSize === '58mm' ? 32 : 48;
    }

    private function center(string $text, int $width): string
    {
        return str_pad($text, $width, ' ', STR_PAD_BOTH);
    }

    private function columns(string $left, string $right, int $width): string
    {
        $left = substr($left, 0, max(1, $width - strlen($right) - 1));

        return str_pad($left, $width - strlen($right)).$right;
    }
}
