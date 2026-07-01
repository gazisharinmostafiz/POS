<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_id',
        'order_ids',
        'cashier_id',
        'cash_amount',
        'card_amount',
        'total_paid',
        'total_payable',
        'balance',
        'change_amount',
        'status',
        'provider',
        'provider_transaction_id',
        'terminal_id',
        'provider_metadata',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'order_id' => 'integer',
        'order_ids' => 'array',
        'cashier_id' => 'integer',
        'cash_amount' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'balance' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'provider_metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
