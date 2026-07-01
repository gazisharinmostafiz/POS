<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_id',
        'menu_item_id',
        'item_name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'line_total',
        'item_note',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'order_id' => 'integer',
        'menu_item_id' => 'integer',
        'quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
