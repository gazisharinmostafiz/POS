<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_PLATFORM = 'platform';

    public const TYPE_DATABASE = 'database';
    public const TYPE_UPLOADS = 'uploads';
    public const TYPE_INVOICES = 'invoices';
    public const TYPE_FULL_TENANT = 'full_tenant';
    public const TYPE_FULL_PLATFORM = 'full_platform';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'scope',
        'type',
        'status',
        'disk',
        'path',
        'filename',
        'checksum',
        'size_bytes',
        'encrypted',
        'remote_disk',
        'metadata',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'created_by' => 'integer',
        'size_bytes' => 'integer',
        'encrypted' => 'boolean',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }
}
