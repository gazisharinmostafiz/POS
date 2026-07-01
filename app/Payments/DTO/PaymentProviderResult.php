<?php

namespace App\Payments\DTO;

class PaymentProviderResult
{
    public function __construct(
        public string $provider,
        public string $status,
        public float $amount,
        public ?string $providerTransactionId = null,
        public ?string $terminalId = null,
        public array $metadata = [],
        public ?string $message = null
    ) {
    }
}
