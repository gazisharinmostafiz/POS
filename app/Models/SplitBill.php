<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitBill extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_id',
        'order_ids',
        'cashier_id',
        'people_count',
        'total_payable',
        'amount_per_person',
        'rounding_difference',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'order_id' => 'integer',
        'order_ids' => 'array',
        'cashier_id' => 'integer',
        'people_count' => 'integer',
        'total_payable' => 'decimal:2',
        'amount_per_person' => 'decimal:2',
        'rounding_difference' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
