<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockService
{
    use Concerns;

    /** Lock (creating if needed) the balance row for a shop/product. Must run inside a transaction. */
    public function lockBalance(int $shopId, int $productId): StockBalance
    {
        StockBalance::firstOrCreate(['shop_id' => $shopId, 'product_id' => $productId], ['quantity' => 0]);

        return StockBalance::where('shop_id', $shopId)->where('product_id', $productId)->lockForUpdate()->first();
    }

    public function available(int $shopId, int $productId): float
    {
        return (float) (StockBalance::where('shop_id', $shopId)->where('product_id', $productId)->value('quantity') ?? 0);
    }

    /** Cost per unit for a sale according to the configured costing method (OD-002). */
    public function unitCost(StockBalance $balance, Product $product): float
    {
        return Money::round(match (Settings::get('costing_method')) {
            'latest_cost' => (float) $balance->last_cost > 0 ? $balance->last_cost : $product->cost_price,
            'product_cost' => $product->cost_price,
            default => (float) $balance->avg_cost > 0 ? $balance->avg_cost : $product->cost_price,
        });
    }

    /**
     * Apply a signed quantity change and write the append-only movement record.
     */
    public function move(
        int $shopId,
        int $productId,
        float $delta,
        string $type,
        ?Model $source,
        ?User $user,
        ?float $unitCost = null,
        ?string $reason = null,
        bool $allowNegative = false,
    ): StockMovement {
        $balance = $this->lockBalance($shopId, $productId);
        $before = Money::qty($balance->quantity);
        $after = Money::qty($before + $delta);

        if ($after < 0 && $delta < 0 && ! $allowNegative && Settings::get('negative_stock_policy') !== 'allow') {
            $product = Product::find($productId);
            $this->fail('insufficient_stock', __('Insufficient stock for :product: available :available, requested :requested.', [
                'product' => $product?->name ?? "#{$productId}", 'available' => Money::formatQty($before), 'requested' => Money::formatQty(abs($delta)),
            ]),
                true, ['product_id' => $productId, 'available' => $before, 'requested' => abs($delta)]);
        }

        if ($type === 'purchase' && $delta > 0 && $unitCost !== null) {
            $base = max($before, 0);
            $balance->avg_cost = Money::round($base + $delta > 0
                ? (($base * (float) $balance->avg_cost) + ($delta * $unitCost)) / ($base + $delta)
                : $unitCost);
            $balance->last_cost = Money::round($unitCost);
        }

        $balance->quantity = $after;
        $balance->save();

        $movement = StockMovement::create([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'user_id' => $user?->id,
            'type' => $type,
            'quantity' => Money::qty($delta),
            'before_qty' => $before,
            'after_qty' => $after,
            'unit_cost' => $unitCost,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reason' => $reason,
        ]);

        DB::afterCommit(fn () => NotificationService::checkLowStock($balance->fresh()));

        return $movement;
    }

    /**
     * Manual stock adjustment (Doc 14 / BR-005). Accepts either direction+quantity
     * or counted_quantity (physical count) from which the difference is derived.
     */
    public function adjust(array $data, User $user, array $options = []): StockAdjustment
    {
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'product_id' => 'nullable|integer',
            'product_local_uuid' => 'nullable|uuid',
            'direction' => 'required_without:counted_quantity|nullable|in:in,out',
            'quantity' => 'required_without:counted_quantity|nullable|numeric|gt:0',
            'counted_quantity' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(StockAdjustment::class, $data)) {
            return $existing;
        }

        $this->requirePermission($user, 'adjust_stock', __('You are not allowed to adjust stock.'));
        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        $productId = $this->resolveRef(Product::class, $data, 'product_id', 'product_local_uuid', 'product')
            ?? $this->fail('validation', __('A product is required.'));

        return DB::transaction(function () use ($data, $user, $shop, $productId) {
            $balance = $this->lockBalance($shop->id, $productId);
            if (isset($data['counted_quantity']) && $data['counted_quantity'] !== null) {
                $diff = Money::qty((float) $data['counted_quantity'] - (float) $balance->quantity);
                if ($diff == 0.0) {
                    $this->fail('no_change', __('Counted quantity equals current stock; nothing to adjust.'));
                }
                $direction = $diff > 0 ? 'in' : 'out';
                $qty = abs($diff);
            } else {
                $direction = $data['direction'];
                $qty = Money::qty($data['quantity']);
            }

            $adjustment = StockAdjustment::create(array_merge([
                'shop_id' => $shop->id,
                'product_id' => $productId,
                'user_id' => $user->id,
                'direction' => $direction,
                'quantity' => $qty,
                'before_qty' => $balance->quantity,
                'after_qty' => $balance->quantity,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ], $this->syncAttributes($data)));

            $movement = $this->move($shop->id, $productId, $direction === 'in' ? $qty : -$qty,
                $direction === 'in' ? 'adjustment_in' : 'adjustment_out', $adjustment, $user, null, $data['reason'],
                allowNegative: true);

            $adjustment->update(['before_qty' => $movement->before_qty, 'after_qty' => $movement->after_qty]);
            AuditLogger::log('stock.adjusted', $adjustment, ['quantity' => (float) $movement->before_qty],
                ['quantity' => (float) $movement->after_qty, 'direction' => $direction, 'reason' => $data['reason']]);

            return $adjustment;
        });
    }
}
