<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalEntry extends Model
{
    protected $fillable = [
        'shop_id', 'daily_session_id', 'user_id', 'type', 'amount', 'entry_date', 'payment_method', 'reason', 'status',
        'voided_by', 'voided_at', 'void_reason',
        'local_uuid', 'device_id', 'source', 'client_created_at', 'synced_at',
    ];

    protected $casts = ['entry_date' => 'date:Y-m-d', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
