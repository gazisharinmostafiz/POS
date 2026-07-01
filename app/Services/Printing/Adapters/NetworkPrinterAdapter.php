<?php

namespace App\Services\Printing\Adapters;

use App\Exceptions\PrinterException;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\Printing\Contracts\PrinterAdapterInterface;
use App\Services\Printing\EscPosContentGenerator;

class NetworkPrinterAdapter implements PrinterAdapterInterface
{
    public function __construct(private ?EscPosContentGenerator $generator = null)
    {
        $this->generator ??= app(EscPosContentGenerator::class);
    }

    public function print(PrintJob $job): void
    {
        $printer = $job->printer;
        $socket = $this->connect($printer);
        $content = $this->generator->generate($job);

        try {
            fwrite($socket, $content);
        } finally {
            fclose($socket);
        }
    }

    public function testConnection(Printer $printer): bool
    {
        $socket = $this->connect($printer);
        fclose($socket);

        return true;
    }

    private function connect(Printer $printer)
    {
        $host = $printer->ip_address ?: ($printer->connection_settings['ip_address'] ?? null);
        $port = $printer->port ?: ($printer->connection_settings['port'] ?? 9100);

        if (! $host) {
            throw new PrinterException('Printer IP address is not configured.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 3);

        if (! $socket) {
            throw new PrinterException($errstr ?: 'Unable to connect to network printer.');
        }

        stream_set_timeout($socket, 5);

        return $socket;
    }
}
