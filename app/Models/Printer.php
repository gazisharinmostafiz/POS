<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    use HasFactory;

    public const TYPE_BROWSER = 'browser';
    public const TYPE_NETWORK = 'network';
    public const TYPE_EPSON_EPOS = 'epson_epos';
    public const TYPE_STAR_CLOUDPRNT = 'star_cloudprnt';
    public const TYPE_LOCAL_BRIDGE = 'local_bridge';

    public const TYPES = [
        self::TYPE_BROWSER,
        self::TYPE_NETWORK,
        self::TYPE_EPSON_EPOS,
        self::TYPE_STAR_CLOUDPRNT,
        self::TYPE_LOCAL_BRIDGE,
    ];

    public const ROLE_RECEIPT = 'receipt';
    public const ROLE_KITCHEN = 'kitchen';
    public const ROLE_BOTH = 'both';

    public const ROLES = [
        self::ROLE_RECEIPT,
        self::ROLE_KITCHEN,
        self::ROLE_BOTH,
    ];

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'type',
        'role',
        'ip_address',
        'port',
        'paper_size',
        'connection_settings',
        'kitchen_category_routes',
        'bridge_token_hash',
        'bridge_status',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'port' => 'integer',
        'connection_settings' => 'array',
        'kitchen_category_routes' => 'array',
        'last_seen_at' => 'datetime',
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

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }

    public function scopeForBranch(Builder $query, ?Branch $branch): Builder
    {
        return $query->where(function ($query) use ($branch) {
            $query->whereNull('branch_id');

            if ($branch) {
                $query->orWhere('branch_id', $branch->id);
            }
        });
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereIn('role', [$role, self::ROLE_BOTH]);
    }
}
