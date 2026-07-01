<?php

namespace App\Exceptions;

class PaymentProviderException extends \RuntimeException
{
    public ?string $provider;

    public ?array $context;

    public function __construct(
        string $message,
        ?string $provider = null,
        ?array $context = null
    ) {
        $this->provider = $provider;
        $this->context = $context;

        parent::__construct($message);
    }
}
