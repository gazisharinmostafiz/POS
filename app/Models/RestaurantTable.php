<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    protected $table = 'tables';

    public const STATUS_FREE = 'free';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_ORDER_SENT = 'order_sent';
    public const STATUS_COOKING = 'cooking';
    public const STATUS_READY = 'ready';
    public const STATUS_PENDING_BILL = 'pending_bill';
    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_FREE,
        self::STATUS_OCCUPIED,
        self::STATUS_ORDER_SENT,
        self::STATUS_COOKING,
        self::STATUS_READY,
        self::STATUS_PENDING_BILL,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'number',
        'label',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }

    public function updateStatus(string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid table status [{$status}].");
        }

        return $this->forceFill(['status' => $status])->save();
    }
}
