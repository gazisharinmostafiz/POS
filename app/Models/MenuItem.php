<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'category_id',
        'name',
        'description',
        'price',
        'image_path',
        'is_available',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }

    public function scopeForBranch(Builder $query, ?Branch $branch): Builder
    {
        return $branch
            ? $query->where(function (Builder $query) use ($branch) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            : $query->whereNull('branch_id');
    }

    public function scopeVisibleToWaiter(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true));
    }
}
