<?php

namespace App\Payments\DTO;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

class PaymentProviderRequest
{
    public function __construct(
        public Tenant $tenant,
        public ?int $branchId,
        public Order $order,
        public User $cashier,
        public float $amount,
        public ?string $terminalId = null,
        public ?string $manualReference = null,
        public array $metadata = []
    ) {
    }
}
