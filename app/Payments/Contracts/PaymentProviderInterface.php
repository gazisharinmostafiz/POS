<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\DTO\PaymentProviderRequest;
use App\Payments\DTO\PaymentProviderResult;

interface PaymentProviderInterface
{
    public function key(): string;

    public function charge(PaymentProviderRequest $request): PaymentProviderResult;

    public function status(Payment $payment): PaymentProviderResult;

    public function cancel(Payment $payment): PaymentProviderResult;

    public function refund(Payment $payment, ?float $amount = null): PaymentProviderResult;
}
