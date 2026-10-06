<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'reference', 'sale_id', 'shop_id', 'daily_session_id', 'user_id', 'return_date', 'return_value', 'debt_reduction',
        'refund_amount', 'refund_method', 'cost_restocked', 'reason', 'local_uuid', 'device_id', 'source',
    ];

    protected $casts = [
        'return_date' => 'date:Y-m-d', 'return_value' => 'decimal:2', 'debt_reduction' => 'decimal:2',
        'refund_amount' => 'decimal:2', 'cost_restocked' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
