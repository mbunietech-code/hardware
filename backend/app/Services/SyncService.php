<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Device;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SyncReceipt;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\ActionContext;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Offline sync (Doc 09). Each pushed item carries a local_uuid generated on the device
 * before first save; receipts keyed by (local_uuid, entity) make retries idempotent so
 * a record is never counted twice. Results are accepted / rejected / conflict / retry.
 */
class SyncService
{
    public const ENTITIES = [
        'customer', 'supplier', 'product', 'daily_session_open', 'daily_session_close', 'sale', 'purchase',
        'expense', 'capital_entry', 'debt', 'debt_payment', 'stock_adjustment',
    ];

    public function __construct(
        private PartyService $parties,
        private CatalogService $catalog,
        private DailySessionService $sessions,
        private SaleService $sales,
        private PurchaseService $purchases,
        private ExpenseService $expenses,
        private CapitalService $capital,
        private DebtService $debts,
        private StockService $stock,
    ) {}

    /** @param array<int, array{entity: string, local_uuid: string, payload: array}> $items */
    public function push(array $items, User $user, ?string $deviceId): array
    {
        $ctx = ActionContext::current();
        $ctx->source = 'sync';
        $ctx->deviceId = $deviceId;

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->pushOne($item, $user, $deviceId);
        }

        if ($deviceId) {
            Device::where('user_id', $user->id)->where('device_id', $deviceId)->update(['last_sync_at' => now()]);
        }
        $summary = collect($results)->countBy('status')->all();
        if ($items) {
            AuditLogger::log('sync.push', null, null, ['items' => count($items), 'results' => $summary], $user->shop_id);
        }

        return ['results' => $results, 'summary' => $summary, 'server_time' => now()->toIso8601String()];
    }

    private function pushOne(array $item, User $user, ?string $deviceId): array
    {
        $entity = $item['entity'] ?? null;
        $uuid = $item['local_uuid'] ?? null;
        $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
        $base = ['entity' => $entity, 'local_uuid' => $uuid];

        if (! in_array($entity, self::ENTITIES, true) || ! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return $base + ['status' => 'rejected', 'error_code' => 'invalid_item', 'message' => __('Unknown entity or missing local_uuid.')];
        }

        $receipt = SyncReceipt::where('local_uuid', $uuid)->where('entity', $entity)->first();
        if ($receipt && in_array($receipt->status, ['accepted', 'conflict'], true)) {
            // Retry of something we already processed: return the original acknowledgement.
            return $this->result($receipt) + ['duplicate' => true];
        }

        $payload['local_uuid'] = $uuid;
        $shopId = $payload['shop_id'] ?? $user->shop_id;

        try {
            $model = $this->apply($entity, $payload, $user);
            $receipt = $this->saveReceipt($uuid, $entity, $user, $shopId, $deviceId, [
                'status' => 'accepted', 'server_id' => $model->getKey(), 'error_code' => null, 'error_message' => null, 'payload' => $payload,
            ]);

            return $this->result($receipt, $model);
        } catch (ValidationException $e) {
            $receipt = $this->saveReceipt($uuid, $entity, $user, $shopId, $deviceId, [
                'status' => 'rejected', 'error_code' => 'validation', 'error_message' => collect($e->errors())->flatten()->implode(' '), 'payload' => $payload,
            ]);

            return $this->result($receipt) + ['errors' => $e->errors()];
        } catch (AuthorizationException $e) {
            $receipt = $this->saveReceipt($uuid, $entity, $user, $shopId, $deviceId, [
                'status' => 'rejected', 'error_code' => 'forbidden', 'error_message' => $e->getMessage(), 'payload' => $payload,
            ]);

            return $this->result($receipt);
        } catch (BusinessRuleException $e) {
            $receipt = $this->saveReceipt($uuid, $entity, $user, $shopId, $deviceId, [
                'status' => $e->conflict ? 'conflict' : 'rejected', 'error_code' => $e->errorCode, 'error_message' => $e->getMessage(), 'payload' => $payload,
            ]);
            if ($e->conflict) {
                NotificationService::syncProblem($receipt);
            }

            return $this->result($receipt);
        } catch (Throwable $e) {
            // Unexpected server problem: the device keeps the record and retries later.
            Log::error('Sync item failed', ['entity' => $entity, 'local_uuid' => $uuid, 'error' => $e->getMessage()]);

            return $base + ['status' => 'retry', 'error_code' => 'server_error', 'message' => __('Temporary server problem. The record will be retried.')];
        }
    }

    public function apply(string $entity, array $payload, User $user, array $options = []): Model
    {
        return match ($entity) {
            'customer' => $this->parties->createCustomer($payload, $user),
            'supplier' => $this->parties->createSupplier($payload, $user),
            'product' => $this->catalog->saveProduct($payload, $user),
            'daily_session_open' => $this->sessions->open($payload, $user),
            'daily_session_close' => $this->closeSession($payload, $user),
            'sale' => $this->sales->create($payload, $user, $options),
            'purchase' => $this->purchases->create($payload, $user, $options),
            'expense' => $this->expenses->create($payload, $user, $options),
            'capital_entry' => $this->capital->create($payload, $user, $options),
            'debt' => $this->debts->create($payload, $user, $options),
            'debt_payment' => $this->debts->pay($payload, $user, $options),
            'stock_adjustment' => $this->stock->adjust($payload, $user, $options),
        };
    }

    private function closeSession(array $payload, User $user): DailySession
    {
        $session = ! empty($payload['session_local_uuid'])
            ? DailySession::where('local_uuid', $payload['session_local_uuid'])->first()
            : null;
        $session ??= DailySession::where('shop_id', $payload['shop_id'] ?? $user->shop_id)
            ->whereDate('business_date', $payload['business_date'] ?? now()->toDateString())->first();
        if (! $session) {
            throw ValidationException::withMessages(['business_date' => __('No business day found to close.')]);
        }

        return $this->sessions->close($session, $payload, $user);
    }

    /** Super Admin resolution of a held conflict: force-accept with overrides, or reject. */
    public function resolve(SyncReceipt $receipt, string $decision, User $admin): SyncReceipt
    {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can resolve sync conflicts.'));
        }
        if ($receipt->status !== 'conflict') {
            throw new BusinessRuleException('not_conflict', __('Only conflicts can be resolved.'));
        }
        $before = $receipt->toArray();

        if ($decision === 'reject') {
            $receipt->update(['status' => 'rejected', 'resolution' => 'rejected', 'resolved_by' => $admin->id, 'resolved_at' => now()]);
        } else {
            $ctx = ActionContext::current();
            $ctx->source = 'sync';
            $ctx->deviceId = $receipt->device_id;
            $model = $this->apply($receipt->entity, $receipt->payload, $receipt->user ?? $admin,
                ['allow_negative' => true, 'allow_closed_day' => true, 'allow_below_cost' => true]);
            $receipt->update(['status' => 'accepted', 'server_id' => $model->getKey(), 'resolution' => 'force_accepted',
                'resolved_by' => $admin->id, 'resolved_at' => now()]);
        }
        NotificationService::resolve('sync:'.$receipt->id);
        AuditLogger::log('sync.conflict_resolved', $receipt, $before, ['decision' => $decision], $receipt->shop_id, $admin->id);

        return $receipt;
    }

    public function status(array $uuids): array
    {
        return SyncReceipt::whereIn('local_uuid', array_slice($uuids, 0, 500))->get()
            ->map(fn (SyncReceipt $r) => $this->result($r))->values()->all();
    }

    /** Data the device caches for offline work (Doc 12). `since` limits to changed rows. */
    public function pull(User $user, ?string $since): array
    {
        $sinceAt = $since ? Carbon::parse($since)->subSeconds(5) : null;
        $changed = fn ($q) => $sinceAt ? $q->where('updated_at', '>=', $sinceAt) : $q;
        $shopIds = $user->accessibleShopIds();
        $inShops = fn ($q) => $shopIds === null ? $q : $q->whereIn('shop_id', $shopIds);

        return [
            'server_time' => now()->toIso8601String(),
            'full' => $sinceAt === null,
            'shops' => Shop::when($shopIds !== null, fn ($q) => $q->whereIn('id', $shopIds))->get(['id', 'name', 'code', 'location', 'phone', 'is_active']),
            'settings' => array_intersect_key(Settings::all(), array_flip([
                'business_name', 'currency', 'receipt_footer', 'require_open_day', 'negative_stock_policy',
                'sell_below_cost_policy', 'discounts_enabled', 'auto_debt_from_credit',
            ])),
            'categories' => $changed(Category::query())->get(['id', 'name', 'is_active', 'updated_at']),
            'expense_categories' => $changed(ExpenseCategory::query())->get(['id', 'name', 'is_active', 'updated_at']),
            'products' => $changed(Product::query())->get(['id', 'category_id', 'code', 'name', 'unit', 'cost_price', 'selling_price', 'reorder_level', 'is_active', 'local_uuid', 'updated_at']),
            'customers' => $changed(Customer::query())->get(['id', 'name', 'phone', 'is_active', 'local_uuid', 'updated_at']),
            'suppliers' => $changed(Supplier::query())->get(['id', 'name', 'phone', 'is_active', 'local_uuid', 'updated_at']),
            'stock' => $changed($inShops(StockBalance::query()))->get(['shop_id', 'product_id', 'quantity', 'updated_at']),
            'debts' => $changed($inShops(Debt::query()))->get(['id', 'shop_id', 'type', 'customer_id', 'supplier_id', 'party_name', 'party_phone', 'original_amount', 'paid_amount', 'balance', 'debt_date', 'due_date', 'status', 'local_uuid', 'updated_at']),
            'daily_sessions' => $inShops(DailySession::query())->where('business_date', '>=', now()->subDays(7)->toDateString())
                ->get(['id', 'shop_id', 'business_date', 'status', 'opening_cash', 'expected_cash', 'closing_cash', 'totals', 'local_uuid', 'updated_at']),
            'notifications' => SystemNotification::visibleTo($user)->whereNull('read_at')->whereNull('resolved_at')
                ->latest()->limit(50)->get()
                ->map(fn (SystemNotification $n) => ['id' => $n->id, 'type' => $n->type, 'title' => $n->titleText(),
                    'message' => $n->messageText(), 'shop_id' => $n->shop_id, 'created_at' => $n->created_at]),
        ];
    }

    private function saveReceipt(string $uuid, string $entity, User $user, $shopId, ?string $deviceId, array $attrs): SyncReceipt
    {
        return SyncReceipt::updateOrCreate(['local_uuid' => $uuid, 'entity' => $entity], $attrs + [
            'user_id' => $user->id,
            'shop_id' => is_numeric($shopId) && Shop::whereKey($shopId)->exists() ? (int) $shopId : null,
            'device_id' => $deviceId,
        ]);
    }

    private function result(SyncReceipt $r, ?Model $model = null): array
    {
        return array_filter([
            'entity' => $r->entity,
            'local_uuid' => $r->local_uuid,
            'status' => $r->status,
            'server_id' => $r->server_id,
            'reference' => $model?->getAttribute('reference'),
            'error_code' => $r->error_code,
            'message' => $r->error_message,
            'resolution' => $r->resolution,
        ], fn ($v) => $v !== null);
    }
}
