<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only stock history (Doc 14). Never updated or deleted by the application. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'adjustment_in' => 'Adjustment (increase)',
        'adjustment_out' => 'Adjustment (decrease)',
        'sale_void' => 'Sale voided (returned to stock)',
        'sale_return' => 'Customer return (back to stock)',
        'purchase_void' => 'Purchase voided (removed from stock)',
    ];

    protected $fillable = [
        'shop_id', 'product_id', 'user_id', 'type', 'quantity', 'before_qty', 'after_qty',
        'unit_cost', 'source_type', 'source_id', 'reason',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'before_qty' => 'decimal:3', 'after_qty' => 'decimal:3', 'unit_cost' => 'decimal:2'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
