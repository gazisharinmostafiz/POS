<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAccountSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'provider',
        'account_reference',
        'terminal_id',
        'settings',
        'encrypted_credentials',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'settings' => 'array',
        'encrypted_credentials' => 'encrypted:array',
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
}
