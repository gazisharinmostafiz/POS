<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plan_id',
        'slug',
        'domain',
        'subdomain',
        'is_active',
        'subscription_status',
        'subscription_ends_at',
        'stripe_customer_id',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'is_active' => 'boolean',
        'subscription_ends_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }

    public function tenantAddons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(FeatureFlag::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function vendorSubscription(): HasOne
    {
        return $this->hasOne(VendorSubscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        if (in_array($this->subscription_status, ['active', 'trialing'], true)) {
            return $this->subscription_ends_at === null || $this->subscription_ends_at->isFuture();
        }

        return $this->subscription_status === 'past_due'
            && $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isFuture();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
