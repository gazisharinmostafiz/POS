<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'provider',
        'billing_interval',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_subscription_item_id',
        'stripe_price_id',
        'status',
        'trial_ends_at',
        'current_period_ends_at',
        'grace_ends_at',
        'cancelled_at',
        'provider_metadata',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'plan_id' => 'integer',
        'trial_ends_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'provider_metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
