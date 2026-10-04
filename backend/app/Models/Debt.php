<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Debt extends Model
{
    public const TYPES = [
        'receivable' => 'Receivable (customer owes us)',
        'payable' => 'Payable (we owe supplier)',
    ];

    protected $fillable = [
        'shop_id', 'daily_session_id', 'user_id', 'type', 'customer_id', 'supplier_id', 'party_name', 'party_phone',
        'original_amount', 'paid_amount', 'balance', 'debt_date', 'due_date', 'status', 'source_type', 'source_id', 'notes',
        'local_uuid', 'device_id', 'source', 'client_created_at', 'synced_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'balance' => 'decimal:2',
        'debt_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceRecord(): MorphTo
    {
        return $this->morphTo('source');
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->balance > 0 && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
