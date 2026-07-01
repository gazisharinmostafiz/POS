<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicket extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COOKING = 'cooking';
    public const STATUS_READY = 'ready';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_id',
        'ticket_number',
        'status',
        'is_addon',
        'kitchen_note',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'order_id' => 'integer',
        'is_addon' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('created_at')->orderBy('id');
    }
}
