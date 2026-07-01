<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\Adapters\CashAdapter;
use App\Payments\Adapters\ExternalCardAdapter;
use App\Payments\Adapters\TeyaAdapter;
use App\Payments\Adapters\WorldpayAdapter;
use App\Payments\Contracts\PaymentProviderInterface;
use App\Payments\DTO\PaymentProviderRequest;
use App\Payments\DTO\PaymentProviderResult;
use Illuminate\Support\Arr;

class PaymentService
{
    /** @var array<string, PaymentProviderInterface> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [
            'cash' => new CashAdapter(),
            'external_card' => new ExternalCardAdapter(),
            'teya' => new TeyaAdapter(),
            'worldpay' => new WorldpayAdapter(),
            // Future adapters: stripe_terminal.
        ];
    }

    public function adapter(string $provider): PaymentProviderInterface
    {
        if (! isset($this->adapters[$provider])) {
            throw new \InvalidArgumentException("Payment provider [{$provider}] is not configured.");
        }

        return $this->adapters[$provider];
    }

    public function charge(
        Tenant $tenant,
        Order $order,
        User $cashier,
        string $provider,
        float $amount,
        ?string $terminalId = null,
        ?string $manualReference = null,
        array $metadata = []
    ): array {
        $result = $this->adapter($provider)->charge(new PaymentProviderRequest(
            tenant: $tenant,
            branchId: $order->branch_id,
            order: $order,
            cashier: $cashier,
            amount: $amount,
            terminalId: $terminalId,
            manualReference: $manualReference,
            metadata: $metadata
        ));

        if ($result->status === 'failed') {
            throw new PaymentProviderException(
                $result->message ?: 'Payment provider declined the payment.',
                $result->provider,
                $result->metadata
            );
        }

        return $this->resultToArray($result);
    }

    public function status(Payment $payment): array
    {
        return $this->resultToArray($this->adapter($payment->provider)->status($payment));
    }

    public function cancel(Payment $payment): array
    {
        return $this->resultToArray($this->adapter($payment->provider)->cancel($payment));
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        return $this->resultToArray($this->adapter($payment->provider)->refund($payment, $amount));
    }

    public function applyProviderResult(Payment $payment, array $providerResult): Payment
    {
        $payment->forceFill([
            'provider' => $providerResult['provider'] ?? null,
            'provider_transaction_id' => $providerResult['provider_transaction_id'] ?? null,
            'terminal_id' => $providerResult['terminal_id'] ?? null,
            'provider_metadata' => $this->safeMetadata($providerResult['metadata'] ?? []),
        ])->save();

        return $payment->fresh();
    }

    public function storeWebhookEvent(
        string $provider,
        array $payload,
        array $headers = [],
        ?Tenant $tenant = null,
        ?int $branchId = null,
        bool $signatureVerified = false
    ): WebhookEvent {
        return WebhookEvent::query()->create([
            'tenant_id' => $tenant?->id,
            'branch_id' => $branchId,
            'provider' => $provider,
            'event_id' => Arr::get($payload, 'id') ?? Arr::get($payload, 'event_id'),
            'event_type' => Arr::get($payload, 'type') ?? Arr::get($payload, 'event_type'),
            'payload' => $this->safeMetadata($payload),
            'headers' => $this->safeMetadata($headers),
            'signature_verified' => $signatureVerified,
        ]);
    }

    public function safeMetadata(array $metadata): array
    {
        $blocked = [
            'card_number',
            'cardNumber',
            'cvv',
            'cvc',
            'pin',
            'pan',
            'expiry',
            'expiry_date',
            'security_code',
            'track_data',
            'trackData',
            'track1',
            'track2',
            'magstripe',
        ];

        return collect($metadata)
            ->reject(fn ($value, $key) => in_array($key, $blocked, true))
            ->map(fn ($value) => is_array($value) ? $this->safeMetadata($value) : $value)
            ->all();
    }

    private function resultToArray(PaymentProviderResult $result): array
    {
        return [
            'provider' => $result->provider,
            'provider_transaction_id' => $result->providerTransactionId,
            'terminal_id' => $result->terminalId,
            'amount' => $result->amount,
            'status' => $result->status,
            'metadata' => $this->safeMetadata($result->metadata),
            'message' => $result->message,
        ];
    }
}
