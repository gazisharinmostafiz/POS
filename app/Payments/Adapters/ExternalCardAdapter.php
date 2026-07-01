<?php

namespace App\Payments\Adapters;

use App\Exceptions\PaymentProviderException;
use App\Models\Payment;
use App\Models\Order;
use App\Payments\Contracts\PaymentProviderInterface;
use App\Payments\DTO\PaymentProviderRequest;
use App\Payments\DTO\PaymentProviderResult;

class ExternalCardAdapter implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'external_card';
    }

    public function charge(PaymentProviderRequest $request): PaymentProviderResult
    {
        return new PaymentProviderResult(
            provider: $this->key(),
            status: Order::PAYMENT_PAID,
            amount: $request->amount,
            providerTransactionId: $request->manualReference ?: 'manual-card-'.$request->order->id.'-'.now()->format('YmdHis'),
            terminalId: $request->terminalId,
            metadata: $this->sanitizeMetadata($request->metadata)
        );
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return array_diff_key($metadata, array_flip(['card_number', 'cvv', 'cvc', 'pan']));
    }

    public function status(Payment $payment): PaymentProviderResult
    {
        return new PaymentProviderResult($this->key(), $payment->status, (float) $payment->card_amount, $payment->provider_transaction_id, $payment->terminal_id);
    }

    public function cancel(Payment $payment): PaymentProviderResult
    {
        throw new PaymentProviderException('External terminal payments must be cancelled on the terminal.', $this->key());
    }

    public function refund(Payment $payment, ?float $amount = null): PaymentProviderResult
    {
        throw new PaymentProviderException('External terminal refunds are a manual placeholder for now.', $this->key());
    }
}
