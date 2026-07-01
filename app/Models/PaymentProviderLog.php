<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProviderLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'payment_id',
        'provider',
        'action',
        'request_payload',
        'response_payload',
        'status_code',
        'success',
        'error_message',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'payment_id' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'status_code' => 'integer',
        'success' => 'boolean',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
