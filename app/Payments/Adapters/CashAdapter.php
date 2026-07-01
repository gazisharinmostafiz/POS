<?php

namespace App\Payments\Adapters;

use App\Exceptions\PaymentProviderException;
use App\Models\Payment;
use App\Models\Order;
use App\Payments\Contracts\PaymentProviderInterface;
use App\Payments\DTO\PaymentProviderRequest;
use App\Payments\DTO\PaymentProviderResult;

class CashAdapter implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'cash';
    }

    public function charge(PaymentProviderRequest $request): PaymentProviderResult
    {
        return new PaymentProviderResult(
            provider: $this->key(),
            status: Order::PAYMENT_PAID,
            amount: $request->amount,
            providerTransactionId: 'cash-'.$request->order->id.'-'.now()->format('YmdHis'),
            terminalId: null,
            metadata: $this->sanitizeMetadata($request->metadata)
        );
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return array_diff_key($metadata, array_flip(['card_number', 'cvv', 'cvc', 'pan']));
    }

    public function status(Payment $payment): PaymentProviderResult
    {
        return new PaymentProviderResult($this->key(), $payment->status, (float) $payment->cash_amount, $payment->provider_transaction_id);
    }

    public function cancel(Payment $payment): PaymentProviderResult
    {
        throw new PaymentProviderException('Cash payments cannot be cancelled through a provider.', $this->key());
    }

    public function refund(Payment $payment, ?float $amount = null): PaymentProviderResult
    {
        throw new PaymentProviderException('Cash refund handling is not connected to a provider.', $this->key());
    }
}
