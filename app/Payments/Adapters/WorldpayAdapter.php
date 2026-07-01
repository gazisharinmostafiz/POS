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

class WorldpayAdapter implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'worldpay';
    }

    public function charge(PaymentProviderRequest $request): PaymentProviderResult
    {
        $settings = $this->settings($request->tenant->id, $request->branchId);
        $credentials = $settings->encrypted_credentials ?? [];
        $product = $credentials['product'] ?? 'terminal';
        $terminalId = $request->terminalId ?: $settings->terminal_id;
        $payload = $this->paymentPayload($request, $product, $terminalId);

        $response = $this->request($settings, 'create_authorization', 'post', $this->paymentPath($product), $payload);

        if (! $response->successful()) {
            throw new PaymentProviderException($this->errorMessage($response, 'Worldpay payment authorization failed.'), $this->key(), $response->json());
        }

        $body = $response->json();

        return new PaymentProviderResult(
            provider: $this->key(),
            status: $this->normalizeStatus($body['status'] ?? $body['outcome'] ?? 'pending'),
            amount: $request->amount,
            providerTransactionId: $body['id'] ?? $body['transaction_id'] ?? $body['paymentId'] ?? null,
            terminalId: $terminalId,
            metadata: app(PaymentService::class)->safeMetadata([
                'merchant_reference' => $payload['merchant_reference'],
                'product' => $product,
                'raw_status' => $body['status'] ?? $body['outcome'] ?? null,
                'hosted_payment_url' => $body['hosted_payment_url'] ?? $body['url'] ?? null,
            ]),
            message: $body['message'] ?? null
        );
    }

    public function status(Payment $payment): PaymentProviderResult
    {
        $settings = $this->settings($payment->tenant_id, $payment->branch_id);
        $product = ($settings->encrypted_credentials ?? [])['product'] ?? 'terminal';
        $response = $this->request($settings, 'payment_status', 'get', $this->statusPath($product, (string) $payment->provider_transaction_id));

        if (! $response->successful()) {
            throw new PaymentProviderException($this->errorMessage($response, 'Unable to retrieve Worldpay payment status.'), $this->key(), $response->json());
        }

        $body = $response->json();

        return new PaymentProviderResult(
            provider: $this->key(),
            status: $this->normalizeStatus($body['status'] ?? $body['outcome'] ?? $payment->status),
            amount: (float) $payment->card_amount,
            providerTransactionId: $payment->provider_transaction_id,
            terminalId: $payment->terminal_id,
            metadata: ['product' => $product, 'raw_status' => $body['status'] ?? $body['outcome'] ?? null],
            message: $body['message'] ?? null
        );
    }

    public function cancel(Payment $payment): PaymentProviderResult
    {
        throw new PaymentProviderException('Worldpay cancellation is not enabled in this adapter foundation.', $this->key());
    }

    public function refund(Payment $payment, ?float $amount = null): PaymentProviderResult
    {
        throw new PaymentProviderException('Worldpay refund support is reserved as a provider placeholder.', $this->key());
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
            throw new PaymentProviderException('Worldpay payment provider is not configured for this tenant or branch.', $this->key());
        }

        return $settings;
    }

    private function paymentPayload(PaymentProviderRequest $request, string $product, ?string $terminalId): array
    {
        $payload = [
            'amount' => [
                'value' => (int) round($request->amount * 100),
                'currency' => $request->metadata['currency'] ?? 'GBP',
            ],
            'merchant_reference' => $request->order->order_number,
            'idempotency_key' => (string) Str::uuid(),
            'capture' => true,
        ];

        if ($product === 'hosted') {
            $payload['flow'] = 'hosted';
            $payload['return_url'] = $request->metadata['return_url'] ?? null;
        } else {
            $payload['flow'] = 'terminal';
            $payload['terminal_id'] = $terminalId;
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }

    private function paymentPath(string $product): string
    {
        return $product === 'hosted'
            ? '/hosted/payments'
            : '/terminal/payments';
    }

    private function statusPath(string $product, string $transactionId): string
    {
        return ($product === 'hosted' ? '/hosted/payments/' : '/terminal/payments/').rawurlencode($transactionId);
    }

    private function request(ProviderAccountSetting $settings, string $action, string $method, string $path, array $payload = []): Response
    {
        $credentials = $settings->encrypted_credentials ?? [];
        $baseUrl = rtrim($credentials['base_url'] ?? 'https://try.access.worldpay.com', '/');
        $apiKey = $credentials['api_key'] ?? null;

        if (! $apiKey) {
            throw new PaymentProviderException('Worldpay credentials are missing an API key.', $this->key());
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

            throw new PaymentProviderException('Worldpay provider request failed: '.$exception->getMessage(), $this->key());
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
            'authorized', 'authorised', 'approved', 'captured', 'settled', 'success', 'succeeded' => Order::PAYMENT_PAID,
            'refused', 'declined', 'failed', 'cancelled', 'canceled', 'error' => 'failed',
            default => Order::PAYMENT_PARTIAL,
        };
    }

    private function errorMessage(Response $response, string $fallback): string
    {
        return $response->json('message')
            ?? $response->json('error.message')
            ?? $response->json('error')
            ?? $response->json('description')
            ?? $fallback;
    }
}
