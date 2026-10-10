<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_id', 'quantity', 'unit_value', 'line_value', 'unit_cost', 'restocked'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_value' => 'decimal:2', 'line_value' => 'decimal:2', 'unit_cost' => 'decimal:2', 'restocked' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
