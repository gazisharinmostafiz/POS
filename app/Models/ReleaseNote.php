<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseNote extends Model
{
    protected $fillable = [
        'created_by',
        'version',
        'title',
        'body',
        'is_published',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
