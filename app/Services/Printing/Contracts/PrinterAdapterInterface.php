<?php

namespace App\Services\Printing\Contracts;

use App\Models\PrintJob;
use App\Models\Printer;

interface PrinterAdapterInterface
{
    public function print(PrintJob $job): void;

    public function testConnection(Printer $printer): bool;
}
