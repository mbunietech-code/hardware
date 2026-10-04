<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $fillable = ['shop_id', 'product_id', 'quantity', 'avg_cost', 'last_cost'];

    protected $casts = ['quantity' => 'decimal:3', 'avg_cost' => 'decimal:2', 'last_cost' => 'decimal:2'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
