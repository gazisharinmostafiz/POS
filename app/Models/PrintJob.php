<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    public const TYPE_TEST = 'test';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_KITCHEN_TICKET = 'kitchen_ticket';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'printer_id',
        'order_id',
        'kitchen_ticket_id',
        'payment_id',
        'type',
        'status',
        'payload',
        'attempts',
        'available_at',
        'printed_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'printer_id' => 'integer',
        'order_id' => 'integer',
        'kitchen_ticket_id' => 'integer',
        'payment_id' => 'integer',
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function kitchenTicket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }

    public function retry(): self
    {
        $this->forceFill([
            'status' => self::STATUS_QUEUED,
            'available_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ])->save();

        return $this->fresh();
    }
}
