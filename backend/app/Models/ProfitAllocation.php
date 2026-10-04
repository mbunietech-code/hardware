<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitAllocation extends Model
{
    protected $fillable = [
        'shop_id', 'period_start', 'period_end', 'revenue', 'cost_of_goods', 'expenses', 'profit_amount',
        'primary_percent', 'secondary_percent', 'primary_amount', 'secondary_amount', 'primary_label', 'secondary_label',
        'formula_config', 'status', 'notes', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'formula_config' => 'array', 'approved_at' => 'datetime',
        'revenue' => 'decimal:2', 'cost_of_goods' => 'decimal:2', 'expenses' => 'decimal:2', 'profit_amount' => 'decimal:2',
        'primary_amount' => 'decimal:2', 'secondary_amount' => 'decimal:2',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
