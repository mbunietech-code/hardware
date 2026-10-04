<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    protected $fillable = [
        'debt_id', 'shop_id', 'daily_session_id', 'user_id', 'amount', 'payment_date', 'payment_method', 'notes',
        'local_uuid', 'device_id', 'source', 'client_created_at', 'synced_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date:Y-m-d'];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
