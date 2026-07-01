<?php

namespace App\Services\Billing;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Settings\RestaurantSettingsService;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function generate(Tenant $tenant, Order $order, Payment $payment): string
    {
        abort_unless($order->tenant_id === $tenant->id && $payment->tenant_id === $tenant->id, 404);

        $settings = app(RestaurantSettingsService::class)->get($tenant);
        $currency = $settings['currency_symbol'] ?? 'GBP ';
        $lines = [
            $settings['restaurant_name'] ?? $tenant->name,
            'Invoice for order '.$order->order_number,
            'Payment status: '.$payment->status,
            'Total payable: '.$currency.number_format((float) $payment->total_payable, 2),
            'Cash: '.$currency.number_format((float) $payment->cash_amount, 2),
            'Card: '.$currency.number_format((float) $payment->card_amount, 2),
            'Change: '.$currency.number_format((float) $payment->change_amount, 2),
            $settings['invoice_footer'] ?? '',
        ];

        $path = 'invoices/'.$tenant->id.'/'.$order->order_number.'-'.$payment->id.'.txt';
        Storage::disk('local')->put($path, implode(PHP_EOL, array_filter($lines)));

        return $path;
    }

    public function generatePdf(Tenant $tenant, Order $order, Payment $payment): string
    {
        abort_unless($order->tenant_id === $tenant->id && $payment->tenant_id === $tenant->id, 404);

        $settings = app(RestaurantSettingsService::class)->get($tenant);
        $currency = $settings['currency_symbol'] ?? 'GBP ';
        $lines = [
            $settings['restaurant_name'] ?? $tenant->name,
            'Invoice for order '.$order->order_number,
            'Payment status: '.$payment->status,
            'Total payable: '.$currency.number_format((float) $payment->total_payable, 2),
            'Cash: '.$currency.number_format((float) $payment->cash_amount, 2),
            'Card: '.$currency.number_format((float) $payment->card_amount, 2),
            'Change: '.$currency.number_format((float) $payment->change_amount, 2),
            $settings['invoice_footer'] ?? '',
        ];

        $path = 'invoices/'.$tenant->id.'/'.$order->order_number.'-'.$payment->id.'.pdf';
        Storage::disk('local')->put($path, $this->simplePdf(array_filter($lines)));

        return $path;
    }

    private function simplePdf(array $lines): string
    {
        $textCommands = collect(array_values($lines))
            ->map(fn (string $line, int $index) => '72 '.(730 - ($index * 18)).' Td ('.$this->escapePdfText($line).') Tj')
            ->implode("\n");

        $stream = "BT\n/F1 12 Tf\n".$textCommands."\nET";
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
