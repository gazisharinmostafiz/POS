<?php

namespace App\Http\Controllers\Printers;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\Printing\EscPosContentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterBridgeController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);

        $printer->forceFill([
            'bridge_status' => $request->input('status', 'online'),
            'last_seen_at' => now(),
            'connection_settings' => array_merge($printer->connection_settings ?? [], [
                'bridge_version' => $request->input('bridge_version'),
                'device_name' => $request->input('device_name'),
            ]),
        ])->save();

        return response()->json([
            'printer_id' => $printer->id,
            'status' => $printer->bridge_status,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);
        $jobs = $this->pendingJobs($printer)->get();

        return response()->json([
            'printer_id' => $printer->id,
            'has_jobs' => $jobs->isNotEmpty(),
            'jobs' => $jobs->map(fn (PrintJob $job) => $this->jobSummary($job))->values(),
        ]);
    }

    public function cloudPrntPoll(Request $request): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);
        $job = $this->pendingJobs($printer)->first();

        return response()->json([
            'jobReady' => (bool) $job,
            'mediaTypes' => ['application/json', 'application/vnd.star.starprnt'],
            'jobToken' => $job?->id,
            'jobUrl' => $job ? route('printer-bridge.jobs.show', $job) : null,
            'deleteMethod' => 'POST',
            'printerMAC' => $request->input('printerMAC'),
            'placeholder' => 'CloudPRNT-compatible polling contract; full Star device dialect can be expanded later.',
        ]);
    }

    public function show(Request $request, PrintJob $job, EscPosContentGenerator $generator): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);
        $this->authorizePrinterJob($printer, $job);

        return response()->json([
            'job' => $this->jobSummary($job),
            'payload' => $job->payload,
            'escpos_base64' => base64_encode($generator->generate($job)),
            'plain_text' => $generator->plainText($job),
        ]);
    }

    public function printed(Request $request, PrintJob $job): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);
        $this->authorizePrinterJob($printer, $job);

        $job->forceFill([
            'status' => PrintJob::STATUS_PRINTED,
            'printed_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ])->save();

        return response()->json(['status' => $job->status]);
    }

    public function failed(Request $request, PrintJob $job): JsonResponse
    {
        $printer = $this->authenticatedPrinter($request);
        $this->authorizePrinterJob($printer, $job);

        $job->forceFill([
            'status' => PrintJob::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $request->input('error', 'Bridge reported print failure.'),
        ])->save();

        return response()->json(['status' => $job->status]);
    }

    private function authenticatedPrinter(Request $request): Printer
    {
        $token = $request->bearerToken() ?: $request->header('X-Printer-Token');

        abort_unless($token, 401, 'Printer token is required.');

        $printer = Printer::query()
            ->where('bridge_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        abort_unless($printer, 401, 'Invalid printer token.');

        return $printer;
    }

    private function pendingJobs(Printer $printer)
    {
        return PrintJob::query()
            ->where('printer_id', $printer->id)
            ->where('tenant_id', $printer->tenant_id)
            ->where('status', PrintJob::STATUS_QUEUED)
            ->where(function ($query) {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function authorizePrinterJob(Printer $printer, PrintJob $job): void
    {
        abort_unless($job->printer_id === $printer->id && $job->tenant_id === $printer->tenant_id, 404);
    }

    private function jobSummary(PrintJob $job): array
    {
        return [
            'id' => $job->id,
            'type' => $job->type,
            'status' => $job->status,
            'attempts' => $job->attempts,
            'created_at' => $job->created_at?->toIso8601String(),
            'fetch_url' => route('printer-bridge.jobs.show', $job),
        ];
    }
}
