<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonFeature extends Model
{
    protected $fillable = [
        'addon_id',
        'feature_key',
        'name',
        'enabled',
        'limit',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
