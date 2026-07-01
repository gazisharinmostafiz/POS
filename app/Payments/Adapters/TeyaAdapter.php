<?php

namespace App\Payments\Adapters;

use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProviderLog;
use App\Models\ProviderAccountSetting;
use App\Payments\Contracts\PaymentProviderInterface;
use App\Payments\DTO\PaymentProviderRequest;
use App\Payments\DTO\PaymentProviderResult;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TeyaAdapter implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'teya';
    }

    public function charge(PaymentProviderRequest $request): PaymentProviderResult
    {
        $settings = $this->settings($request->tenant->id, $request->branchId);
        $terminalId = $request->terminalId ?: $settings->terminal_id;
        $payload = [
            'amount' => [
                'value' => (int) round($request->amount * 100),
                'currency' => $request->metadata['currency'] ?? 'GBP',
            ],
            'terminal_id' => $terminalId,
            'merchant_reference' => $request->order->order_number,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->request($settings, 'create_payment', 'post', '/payments', $payload);

        if (! $response->successful()) {
            throw new PaymentProviderException($this->errorMessage($response, 'Teya payment request failed.'), $this->key(), $response->json());
        }

        $body = $response->json();

        return new PaymentProviderResult(
            provider: $this->key(),
            status: $this->normalizeStatus($body['status'] ?? 'pending'),
            amount: $request->amount,
            providerTransactionId: $body['id'] ?? $body['transaction_id'] ?? null,
            terminalId: $terminalId,
            metadata: app(PaymentService::class)->safeMetadata([
                'merchant_reference' => $payload['merchant_reference'],
                'raw_status' => $body['status'] ?? null,
            ]),
            message: $body['message'] ?? null
        );
    }

    public function status(Payment $payment): PaymentProviderResult
    {
        $settings = $this->settings($payment->tenant_id, $payment->branch_id);
        $response = $this->request($settings, 'payment_status', 'get', '/payments/'.$payment->provider_transaction_id);

        if (! $response->successful()) {
            throw new PaymentProviderException($this->errorMessage($response, 'Unable to retrieve Teya payment status.'), $this->key(), $response->json());
        }

        $body = $response->json();

        return new PaymentProviderResult(
            provider: $this->key(),
            status: $this->normalizeStatus($body['status'] ?? $payment->status),
            amount: (float) $payment->card_amount,
            providerTransactionId: $payment->provider_transaction_id,
            terminalId: $payment->terminal_id,
            metadata: ['raw_status' => $body['status'] ?? null]
        );
    }

    public function cancel(Payment $payment): PaymentProviderResult
    {
        $settings = $this->settings($payment->tenant_id, $payment->branch_id);
        $response = $this->request($settings, 'cancel_payment', 'post', '/payments/'.$payment->provider_transaction_id.'/cancel');

        if (! $response->successful()) {
            throw new PaymentProviderException($this->errorMessage($response, 'Unable to cancel Teya payment.'), $this->key(), $response->json());
        }

        $body = $response->json();

        return new PaymentProviderResult(
            provider: $this->key(),
            status: $this->normalizeStatus($body['status'] ?? 'cancelled'),
            amount: (float) $payment->card_amount,
            providerTransactionId: $payment->provider_transaction_id,
            terminalId: $payment->terminal_id,
            metadata: ['raw_status' => $body['status'] ?? null]
        );
    }

    public function refund(Payment $payment, ?float $amount = null): PaymentProviderResult
    {
        throw new PaymentProviderException('Teya refund support is reserved as a provider placeholder.', $this->key());
    }

    private function settings(int $tenantId, ?int $branchId): ProviderAccountSetting
    {
        $settings = ProviderAccountSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', $this->key())
            ->where('is_active', true)
            ->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderByRaw('branch_id is null')
            ->first();

        if (! $settings) {
            throw new PaymentProviderException('Teya payment provider is not configured for this tenant or branch.', $this->key());
        }

        return $settings;
    }

    private function request(ProviderAccountSetting $settings, string $action, string $method, string $path, array $payload = []): Response
    {
        $credentials = $settings->encrypted_credentials ?? [];
        $baseUrl = rtrim($credentials['base_url'] ?? 'https://api.teya.com', '/');
        $apiKey = $credentials['api_key'] ?? null;

        if (! $apiKey) {
            throw new PaymentProviderException('Teya credentials are missing an API key.', $this->key());
        }

        $safePayload = app(PaymentService::class)->safeMetadata($payload);

        try {
            $client = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout((int) ($credentials['timeout'] ?? 15));

            $response = $method === 'get'
                ? $client->get($baseUrl.$path)
                : $client->{$method}($baseUrl.$path, $payload);

            $this->log($settings, $action, $safePayload, $response->json() ?? [], $response->status(), $response->successful());

            return $response;
        } catch (\Throwable $exception) {
            $this->log($settings, $action, $safePayload, [], null, false, $exception->getMessage());

            throw new PaymentProviderException('Teya provider request failed: '.$exception->getMessage(), $this->key());
        }
    }

    private function log(ProviderAccountSetting $settings, string $action, array $request, array $response, ?int $statusCode, bool $success, ?string $error = null): void
    {
        PaymentProviderLog::query()->create([
            'tenant_id' => $settings->tenant_id,
            'branch_id' => $settings->branch_id,
            'provider' => $this->key(),
            'action' => $action,
            'request_payload' => app(PaymentService::class)->safeMetadata($request),
            'response_payload' => app(PaymentService::class)->safeMetadata($response),
            'status_code' => $statusCode,
            'success' => $success,
            'error_message' => $error,
        ]);
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'approved', 'authorised', 'authorized', 'completed', 'paid', 'success', 'succeeded' => Order::PAYMENT_PAID,
            'failed', 'declined', 'cancelled', 'canceled', 'error' => 'failed',
            default => Order::PAYMENT_PARTIAL,
        };
    }

    private function errorMessage(Response $response, string $fallback): string
    {
        return $response->json('message')
            ?? $response->json('error.message')
            ?? $response->json('error')
            ?? $fallback;
    }
}
