<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\ActionContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer returns (Doc 14 "Return to stock", OD-007).
 *
 * - Return value = net price paid per unit (after line and sale discounts) × quantity.
 * - Restocked items go back on the shelf and their cost leaves COGS; damaged items stay a loss.
 * - On a credit sale the customer's outstanding debt is reduced first; the rest is refunded.
 */
class ReturnService
{
    use Concerns;

    public function __construct(private StockService $stock, private DailySessionService $sessions) {}

    /** Quantity of each sale item that can still be returned. */
    public function returnable(Sale $sale): array
    {
        $returned = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.sale_id', $sale->id)
            ->groupBy('sale_item_id')->pluck(DB::raw('SUM(quantity)'), 'sale_item_id');

        return $sale->items->mapWithKeys(fn (SaleItem $i) => [$i->id => Money::qty((float) $i->quantity - (float) ($returned[$i->id] ?? 0))])->all();
    }

    /** Net value the customer actually paid for one unit of this line. */
    public function unitValue(Sale $sale, SaleItem $item): float
    {
        $factor = (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1;

        return Money::round((float) $item->line_total / max((float) $item->quantity, 0.001) * $factor);
    }

    public function create(Sale $sale, array $data, User $user, array $options = []): SaleReturn
    {
        $data = Validator::make($data, [
            'items' => 'required|array',
            'items.*.sale_item_id' => 'nullable|integer|required_without:items.*.product_id',
            'items.*.product_id' => 'nullable|integer',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.restock' => 'nullable|boolean',
            'refund_method' => 'nullable|in:'.implode(',', array_diff(self::PAYMENT_METHODS, ['credit'])),
            'reason' => 'required|string|max:255',
            'local_uuid' => 'nullable|uuid',
        ])->validate();

        if ($existing = $this->findByLocalUuid(SaleReturn::class, $data)) {
            return $existing;
        }
        $this->requirePermission($user, 'process_returns', __('You are not allowed to process returns.'));
        $this->authorizeShop($user, $sale->shop_id);
        if ($sale->status !== 'completed') {
            $this->fail('sale_not_completed', __('Only completed sales can have returns.'));
        }

        return DB::transaction(function () use ($sale, $data, $user, $options) {
            $sale = Sale::with('items.product')->lockForUpdate()->find($sale->id);
            $date = now()->toDateString();
            $session = $this->sessions->sessionFor($sale->shop_id, $date, $options);
            $available = $this->returnable($sale);

            $lines = [];
            foreach ($data['items'] as $i => $row) {
                $qty = Money::qty($row['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                // The mobile app only knows product ids; the web form sends sale item ids.
                $item = (! empty($row['sale_item_id'])
                    ? $sale->items->firstWhere('id', (int) $row['sale_item_id'])
                    : $sale->items->first(fn ($i) => $i->product_id === (int) ($row['product_id'] ?? 0) && ($available[$i->id] ?? 0) > 0))
                    ?? throw ValidationException::withMessages(["items.$i.sale_item_id" => __('This item is not part of the sale.')]);
                if ($qty > ($available[$item->id] ?? 0) + 0.0005) {
                    throw ValidationException::withMessages(["items.$i.quantity" => __('Only :n of :product can still be returned.', ['n' => Money::formatQty($available[$item->id] ?? 0), 'product' => $item->product->name])]);
                }
                $unit = $this->unitValue($sale, $item);
                $lines[] = ['item' => $item, 'qty' => $qty, 'unit' => $unit, 'value' => Money::round($unit * $qty), 'restock' => (bool) ($row['restock'] ?? true)];
            }
            if (! $lines) {
                throw ValidationException::withMessages(['items' => __('Enter the quantity to return for at least one item.')]);
            }

            $value = Money::round(array_sum(array_column($lines, 'value')));
            $debt = Debt::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)
                ->whereIn('status', ['open', 'partial'])->lockForUpdate()->first();
            $debtReduction = $debt ? min($value, (float) $debt->balance) : 0.0;
            $refund = Money::round($value - $debtReduction);

            $return = SaleReturn::create([
                'reference' => 'TMP-'.Str::uuid(),
                'sale_id' => $sale->id,
                'shop_id' => $sale->shop_id,
                'daily_session_id' => $session?->id,
                'user_id' => $user->id,
                'return_date' => $date,
                'return_value' => $value,
                'debt_reduction' => Money::round($debtReduction),
                'refund_amount' => $refund,
                'refund_method' => $data['refund_method'] ?? 'cash',
                'reason' => $data['reason'],
                'local_uuid' => $data['local_uuid'] ?? null,
                'source' => ActionContext::current()->source,
                'device_id' => ActionContext::current()->deviceId,
            ]);
            $return->update(['reference' => sprintf('R-%s-%06d', $sale->shop->code, $return->id)]);

            $costRestocked = 0.0;
            foreach ($lines as $l) {
                $return->items()->create([
                    'sale_item_id' => $l['item']->id,
                    'product_id' => $l['item']->product_id,
                    'quantity' => $l['qty'],
                    'unit_value' => $l['unit'],
                    'line_value' => $l['value'],
                    'unit_cost' => $l['item']->unit_cost,
                    'restocked' => $l['restock'],
                ]);
                if ($l['restock']) {
                    $this->stock->move($sale->shop_id, $l['item']->product_id, $l['qty'], 'sale_return', $return, $user,
                        (float) $l['item']->unit_cost, $data['reason'], allowNegative: true);
                    $costRestocked += (float) $l['item']->unit_cost * $l['qty'];
                }
            }
            $return->update(['cost_restocked' => Money::round($costRestocked)]);

            if ($debt && $debtReduction > 0) {
                $balance = Money::round((float) $debt->balance - $debtReduction);
                $debt->update([
                    'original_amount' => Money::round((float) $debt->original_amount - $debtReduction),
                    'balance' => $balance,
                    'status' => $balance <= 0 ? ((float) $debt->paid_amount > 0 ? 'paid' : 'cancelled') : $debt->status,
                    'notes' => trim(($debt->notes ?? '')."\n".__('Reduced by return :ref', ['ref' => $return->reference])),
                ]);
                $sale->balance = $balance;
            }
            $sale->update([
                'returned_total' => Money::round((float) $sale->returned_total + $value),
                'balance' => $sale->balance,
            ]);

            AuditLogger::log('sale.returned', $return, null, $return->fresh()->load('items')->toArray());

            return $return->load('items.product');
        });
    }
}
