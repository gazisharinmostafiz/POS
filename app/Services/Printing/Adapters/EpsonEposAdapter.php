<?php

namespace App\Services\Printing\Adapters;

use App\Exceptions\PrinterException;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\Printing\Contracts\PrinterAdapterInterface;
use App\Services\Printing\EscPosContentGenerator;
use Illuminate\Support\Facades\Http;

class EpsonEposAdapter implements PrinterAdapterInterface
{
    public function __construct(private ?EscPosContentGenerator $generator = null)
    {
        $this->generator ??= app(EscPosContentGenerator::class);
    }

    public function print(PrintJob $job): void
    {
        $printer = $job->printer;
        $response = Http::timeout(5)
            ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
            ->send('POST', $this->endpoint($printer), [
                'body' => $this->xml($job),
            ]);

        if (! $response->successful()) {
            throw new PrinterException('Epson ePOS printer returned HTTP '.$response->status().'.');
        }

        $body = $response->body();

        if (str_contains($body, 'success="false"')) {
            throw new PrinterException('Epson ePOS printer rejected the print request.');
        }
    }

    public function testConnection(Printer $printer): bool
    {
        $response = Http::timeout(5)->get($this->baseUrl($printer));

        if (! $response->successful()) {
            throw new PrinterException('Unable to reach Epson ePOS printer.');
        }

        return true;
    }

    private function xml(PrintJob $job): string
    {
        $text = htmlspecialchars($this->generator->plainText($job), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<s:Body>'
            .'<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">'
            .'<text lang="en">'.$text."\n".'</text>'
            .'<cut type="feed"/>'
            .'</epos-print>'
            .'</s:Body>'
            .'</s:Envelope>';
    }

    private function endpoint(Printer $printer): string
    {
        $settings = $printer->connection_settings ?? [];
        $deviceId = $settings['device_id'] ?? 'local_printer';
        $timeout = $settings['timeout'] ?? 10000;

        return $this->baseUrl($printer).'/cgi-bin/epos/service.cgi?devid='.rawurlencode($deviceId).'&timeout='.(int) $timeout;
    }

    private function baseUrl(Printer $printer): string
    {
        $settings = $printer->connection_settings ?? [];
        $scheme = $settings['scheme'] ?? 'http';
        $host = $printer->ip_address ?: ($settings['ip_address'] ?? null);
        $port = $printer->port ?: ($settings['port'] ?? 80);

        if (! $host) {
            throw new PrinterException('Epson printer IP address is not configured.');
        }

        return $scheme.'://'.$host.':'.(int) $port;
    }
}
