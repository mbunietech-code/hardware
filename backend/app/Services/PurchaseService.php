<?php

namespace App\Services;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    use Concerns;

    public function __construct(
        private StockService $stock,
        private DailySessionService $sessions,
        private PartyService $parties,
    ) {}

    public function create(array $data, User $user, array $options = []): Purchase
    {
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'purchase_date' => 'nullable|date',
            'supplier_id' => 'nullable|integer',
            'supplier_local_uuid' => 'nullable|uuid',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:60',
            'payment_method' => 'required|in:'.implode(',', self::PAYMENT_METHODS),
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_local_uuid' => 'nullable|uuid',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ], [], ['items.*.quantity' => 'quantity', 'items.*.unit_cost' => 'unit cost'])->validate();

        if ($existing = $this->findByLocalUuid(Purchase::class, $data)) {
            return $existing->load('items');
        }

        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        $date = $this->businessDate($data, 'purchase_date');

        return DB::transaction(function () use ($data, $user, $shop, $date, $options) {
            $session = $this->sessions->sessionFor($shop->id, $date, $options);
            $supplierId = $this->resolveRef(Supplier::class, $data, 'supplier_id', 'supplier_local_uuid', 'supplier')
                ?? $this->parties->quickSupplier($data['supplier_name'] ?? null, null, $user);

            $lines = [];
            $total = 0.0;
            foreach ($data['items'] as $i => $item) {
                $productId = $this->resolveRef(Product::class, $item, 'product_id', 'product_local_uuid', 'product')
                    ?? throw ValidationException::withMessages(["items.$i.product_id" => __('Select a product.')]);
                $qty = Money::qty($item['quantity']);
                $cost = Money::round($item['unit_cost']);
                $lineTotal = Money::round($qty * $cost);
                $lines[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_cost' => $cost, 'line_total' => $lineTotal];
                $total += $lineTotal;
            }
            $total = Money::round($total);
            $paid = $data['payment_method'] === 'credit' ? Money::round($data['amount_paid'] ?? 0)
                : Money::round($data['amount_paid'] ?? $total);
            if ($paid > $total) {
                throw ValidationException::withMessages(['amount_paid' => __('Amount paid cannot exceed the purchase total.')]);
            }
            $balance = Money::round($total - $paid);
            if ($balance > 0 && ! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => __('A supplier is required when the purchase is not fully paid.')]);
            }

            $purchase = Purchase::create(array_merge([
                'reference' => 'TMP-'.Str::uuid(),
                'shop_id' => $shop->id,
                'daily_session_id' => $session?->id,
                'supplier_id' => $supplierId,
                'user_id' => $user->id,
                'purchase_date' => $date,
                'invoice_number' => $data['invoice_number'] ?? null,
                'total' => $total,
                'amount_paid' => $paid,
                'balance' => $balance,
                'payment_method' => $data['payment_method'],
                'payment_status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ], $this->syncAttributes($data)));
            $purchase->update(['reference' => sprintf('P-%s-%06d', $shop->code, $purchase->id)]);

            foreach ($lines as $line) {
                $purchase->items()->create($line);
                $this->stock->move($shop->id, $line['product_id'], $line['quantity'], 'purchase', $purchase, $user, $line['unit_cost']);
                if (Settings::get('update_product_cost_on_purchase')) {
                    Product::whereKey($line['product_id'])->update(['cost_price' => $line['unit_cost']]);
                }
            }

            if ($balance > 0 && Settings::get('auto_debt_from_credit')) {
                $supplier = Supplier::find($supplierId);
                Debt::create([
                    'shop_id' => $shop->id,
                    'daily_session_id' => $session?->id,
                    'user_id' => $user->id,
                    'type' => 'payable',
                    'supplier_id' => $supplierId,
                    'party_name' => $supplier->name,
                    'party_phone' => $supplier->phone,
                    'original_amount' => $balance,
                    'balance' => $balance,
                    'debt_date' => $date,
                    'status' => 'open',
                    'source_type' => $purchase->getMorphClass(),
                    'source_id' => $purchase->id,
                    'notes' => 'Credit purchase '.$purchase->reference,
                ]);
            }

            AuditLogger::log('purchase.created', $purchase, null, $purchase->fresh()->load('items')->toArray());
            if (! empty($options['allow_closed_day'])) {
                $this->sessions->refreshTotalsIfClosed($session?->id);
            }

            return $purchase->load('items');
        });
    }

    public function void(Purchase $purchase, string $reason, User $user): Purchase
    {
        $this->requirePermission($user, 'void_transactions', __('You are not allowed to void purchases.'));
        $this->authorizeShop($user, $purchase->shop_id);

        return DB::transaction(function () use ($purchase, $reason, $user) {
            $purchase = Purchase::lockForUpdate()->find($purchase->id);
            if ($purchase->status === 'voided') {
                $this->fail('already_voided', __('This purchase is already voided.'));
            }
            $session = $purchase->daily_session_id ? DailySession::find($purchase->daily_session_id) : null;
            if ($session && ! $session->isOpen() && ! $user->isSuperAdmin()) {
                $this->fail('day_closed', __('This purchase belongs to a closed day. Only a Super Admin can correct it.'));
            }
            $debt = Debt::where('source_type', $purchase->getMorphClass())->where('source_id', $purchase->id)->first();
            if ($debt && $debt->payments()->exists()) {
                $this->fail('debt_has_payments', __('This credit purchase has payments recorded. Reverse them before voiding.'));
            }

            $before = $purchase->toArray();
            foreach ($purchase->items as $item) {
                $this->stock->move($purchase->shop_id, $item->product_id, -(float) $item->quantity, 'purchase_void', $purchase,
                    $user, (float) $item->unit_cost, $reason);
            }
            $debt?->update(['status' => 'cancelled', 'balance' => 0]);
            $purchase->update(['status' => 'voided', 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
            AuditLogger::log('purchase.voided', $purchase, $before, ['status' => 'voided', 'reason' => $reason]);
            $this->sessions->refreshTotalsIfClosed($purchase->daily_session_id);

            return $purchase;
        });
    }
}
