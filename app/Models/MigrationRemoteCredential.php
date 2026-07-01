<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationRemoteCredential extends Model
{
    protected $fillable = [
        'created_by',
        'name',
        'host',
        'port',
        'username',
        'encrypted_credentials',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'port' => 'integer',
        'encrypted_credentials' => 'encrypted:array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
