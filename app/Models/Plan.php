<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }
}
