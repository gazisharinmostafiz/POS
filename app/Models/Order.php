<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT_TO_KITCHEN = 'sent_to_kitchen';
    public const STATUS_COOKING = 'cooking';
    public const STATUS_READY = 'ready';
    public const STATUS_CLOSED = 'closed';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_number',
        'source_type',
        'table_number',
        'waiter_id',
        'cashier_id',
        'order_status',
        'payment_status',
        'is_addon',
        'kitchen_note',
        'subtotal',
        'discount_total',
        'discount_type',
        'discount_value',
        'discount_reason',
        'discount_applied_by',
        'service_charge_total',
        'tax_total',
        'total',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'table_number' => 'integer',
        'waiter_id' => 'integer',
        'cashier_id' => 'integer',
        'is_addon' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_applied_by' => 'integer',
        'service_charge_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kitchenTicket(): HasOne
    {
        return $this->hasOne(KitchenTicket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function splitBills(): HasMany
    {
        return $this->hasMany(SplitBill::class);
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }
}
