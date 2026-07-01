<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMigration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'backup_id',
        'created_by',
        'status',
        'version',
        'disk',
        'path',
        'filename',
        'checksum',
        'size_bytes',
        'metadata',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'backup_id' => 'integer',
        'created_by' => 'integer',
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
