<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    use Concerns;

    /** Warnings produced by the last create() call (e.g. selling below cost). */
    public array $warnings = [];

    public function __construct(
        private StockService $stock,
        private DailySessionService $sessions,
        private PartyService $parties,
    ) {}

    public function create(array $data, User $user, array $options = []): Sale
    {
        $this->warnings = [];
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'sale_date' => 'nullable|date',
            'customer_id' => 'nullable|integer',
            'customer_local_uuid' => 'nullable|uuid',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'payment_method' => 'required|in:'.implode(',', self::PAYMENT_METHODS),
            'amount_paid' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_local_uuid' => 'nullable|uuid',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ], [], ['items.*.quantity' => 'quantity', 'items.*.unit_price' => 'unit price'])->validate();

        if ($existing = $this->findByLocalUuid(Sale::class, $data)) {
            return $existing->load('items');
        }

        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        $date = $this->businessDate($data, 'sale_date');
        if (! Settings::get('discounts_enabled') && ((float) ($data['discount'] ?? 0) > 0
                || collect($data['items'])->sum(fn ($i) => (float) ($i['discount'] ?? 0)) > 0)) {
            throw ValidationException::withMessages(['discount' => __('Discounts are disabled in settings.')]);
        }

        return DB::transaction(function () use ($data, $user, $shop, $date, $options) {
            $session = $this->sessions->sessionFor($shop->id, $date, $options);
            $customerId = $this->resolveRef(Customer::class, $data, 'customer_id', 'customer_local_uuid', 'customer')
                ?? $this->parties->quickCustomer($data['customer_name'] ?? null, $data['customer_phone'] ?? null, $user);

            $lines = [];
            $subtotal = 0.0;
            foreach ($data['items'] as $i => $item) {
                $productId = $this->resolveRef(Product::class, $item, 'product_id', 'product_local_uuid', 'product')
                    ?? throw ValidationException::withMessages(["items.$i.product_id" => __('Select a product.')]);
                $product = Product::find($productId);
                if (! $product->is_active) {
                    throw ValidationException::withMessages(["items.$i.product_id" => __(':product is inactive.', ['product' => $product->name])]);
                }
                $qty = Money::qty($item['quantity']);
                $price = Money::round($item['unit_price']);
                $lineDiscount = Money::round($item['discount'] ?? 0);
                $lineTotal = Money::round($qty * $price - $lineDiscount);
                if ($lineTotal < 0) {
                    throw ValidationException::withMessages(["items.$i.discount" => __('Discount is larger than the line amount.')]);
                }
                $lines[] = compact('product', 'qty', 'price', 'lineDiscount', 'lineTotal');
                $subtotal += $lineTotal;
            }

            $subtotal = Money::round($subtotal);
            $discount = Money::round($data['discount'] ?? 0);
            $total = Money::round($subtotal - $discount);
            if ($total < 0) {
                throw ValidationException::withMessages(['discount' => __('Discount is larger than the sale subtotal.')]);
            }
            $paid = $data['payment_method'] === 'credit' ? Money::round($data['amount_paid'] ?? 0)
                : Money::round($data['amount_paid'] ?? $total);
            if ($paid > $total) {
                throw ValidationException::withMessages(['amount_paid' => __('Amount paid cannot exceed the sale total.')]);
            }
            $balance = Money::round($total - $paid);
            if ($balance > 0 && ! $customerId) {
                throw ValidationException::withMessages(['customer_id' => __('A customer is required when the sale is not fully paid.')]);
            }

            $sale = Sale::create(array_merge([
                'reference' => 'TMP-'.Str::uuid(),
                'shop_id' => $shop->id,
                'daily_session_id' => $session?->id,
                'customer_id' => $customerId,
                'user_id' => $user->id,
                'sale_date' => $date,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'amount_paid' => $paid,
                'balance' => $balance,
                'payment_method' => $data['payment_method'],
                'payment_status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ], $this->syncAttributes($data)));
            $sale->update(['reference' => sprintf('S-%s-%06d', $shop->code, $sale->id)]);

            $costTotal = 0.0;
            foreach ($lines as $line) {
                $balanceRow = $this->stock->lockBalance($shop->id, $line['product']->id);
                $unitCost = $this->stock->unitCost($balanceRow, $line['product']);
                $policy = Settings::get('sell_below_cost_policy');
                if ($unitCost > 0 && $line['price'] < $unitCost && $policy !== 'allow') {
                    $msg = __(':product is sold below cost (price :price, cost :cost).', ['product' => $line['product']->name, 'price' => Money::format($line['price']), 'cost' => Money::format($unitCost)]);
                    if ($policy === 'block' && empty($options['allow_below_cost'])) {
                        throw ValidationException::withMessages(['items' => $msg]);
                    }
                    $this->warnings[] = $msg;
                }
                $lineCost = Money::round($unitCost * $line['qty']);
                $costTotal += $lineCost;
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['price'],
                    'discount' => $line['lineDiscount'],
                    'line_total' => $line['lineTotal'],
                    'unit_cost' => $unitCost,
                    'cost_total' => $lineCost,
                ]);
                $this->stock->move($shop->id, $line['product']->id, -$line['qty'], 'sale', $sale, $user, $unitCost,
                    allowNegative: ! empty($options['allow_negative']));
            }
            $sale->update(['cost_total' => Money::round($costTotal)]);

            if ($balance > 0 && Settings::get('auto_debt_from_credit')) {
                $customer = Customer::find($customerId);
                Debt::create([
                    'shop_id' => $shop->id,
                    'daily_session_id' => $session?->id,
                    'user_id' => $user->id,
                    'type' => 'receivable',
                    'customer_id' => $customerId,
                    'party_name' => $customer->name,
                    'party_phone' => $customer->phone,
                    'original_amount' => $balance,
                    'balance' => $balance,
                    'debt_date' => $date,
                    'status' => 'open',
                    'source_type' => $sale->getMorphClass(),
                    'source_id' => $sale->id,
                    'notes' => 'Credit sale '.$sale->reference,
                ]);
            }

            AuditLogger::log('sale.created', $sale, null, $sale->fresh()->load('items')->toArray());
            if (! empty($options['allow_closed_day'])) {
                $this->sessions->refreshTotalsIfClosed($session?->id);
            }

            return $sale->load('items');
        });
    }

    /** Correction path (FR-034): void keeps the record and reverses stock. */
    public function void(Sale $sale, string $reason, User $user): Sale
    {
        $this->requirePermission($user, 'void_transactions', __('You are not allowed to void sales.'));
        $this->authorizeShop($user, $sale->shop_id);

        return DB::transaction(function () use ($sale, $reason, $user) {
            $sale = Sale::lockForUpdate()->find($sale->id);
            if ($sale->status === 'voided') {
                $this->fail('already_voided', __('This sale is already voided.'));
            }
            $this->guardClosedDay($sale->daily_session_id, $user);
            $debt = Debt::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->first();
            if ($debt && $debt->payments()->exists()) {
                $this->fail('debt_has_payments', __('This credit sale has debt repayments recorded. Reverse them before voiding.'));
            }

            $before = $sale->toArray();
            foreach ($sale->items as $item) {
                $this->stock->move($sale->shop_id, $item->product_id, (float) $item->quantity, 'sale_void', $sale, $user,
                    (float) $item->unit_cost, $reason, allowNegative: true);
            }
            $debt?->update(['status' => 'cancelled', 'balance' => 0, 'notes' => trim(($debt->notes ?? '')."\nCancelled: sale voided")]);
            $sale->update(['status' => 'voided', 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
            AuditLogger::log('sale.voided', $sale, $before, ['status' => 'voided', 'reason' => $reason]);
            $this->sessions->refreshTotalsIfClosed($sale->daily_session_id);

            return $sale;
        });
    }

    private function guardClosedDay(?int $sessionId, User $user): void
    {
        $session = $sessionId ? DailySession::find($sessionId) : null;
        if ($session && ! $session->isOpen() && ! $user->isSuperAdmin()) {
            $this->fail('day_closed', __('This transaction belongs to a closed day. Only a Super Admin can correct it.'));
        }
    }
}
