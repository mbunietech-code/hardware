<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySession extends Model
{
    protected $fillable = [
        'shop_id', 'business_date', 'status', 'opened_by', 'opened_at', 'opening_cash', 'opening_notes',
        'closed_by', 'closed_at', 'expected_cash', 'closing_cash', 'cash_difference', 'totals', 'client_totals',
        'closing_notes', 'exceptions', 'reopen_count',
        'local_uuid', 'device_id', 'source', 'client_created_at', 'synced_at',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'totals' => 'array',
        'client_totals' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
