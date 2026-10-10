<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'reference', 'shop_id', 'daily_session_id', 'supplier_id', 'user_id', 'purchase_date', 'invoice_number',
        'total', 'amount_paid', 'balance', 'payment_method', 'payment_status', 'status', 'notes',
        'voided_by', 'voided_at', 'void_reason',
        'local_uuid', 'device_id', 'source', 'client_created_at', 'synced_at',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
        'voided_at' => 'datetime', 'client_created_at' => 'datetime', 'synced_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
