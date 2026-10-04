<?php

namespace App\Services;

use App\Models\CapitalEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Capital is tracked separately from sales and profit (Doc 15, pending owner link). */
class CapitalService
{
    use Concerns;

    public function __construct(private DailySessionService $sessions) {}

    public function create(array $data, User $user, array $options = []): CapitalEntry
    {
        $data = Validator::make($data, [
            'shop_id' => 'nullable|integer',
            'type' => 'required|in:injection,withdrawal',
            'amount' => 'required|numeric|gt:0',
            'entry_date' => 'nullable|date',
            'payment_method' => 'nullable|in:'.implode(',', self::PAYMENT_METHODS),
            'reason' => 'required|string|max:255',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(CapitalEntry::class, $data)) {
            return $existing;
        }
        $this->requirePermission($user, 'record_capital', __('You are not allowed to record capital entries.'));
        if (empty($data['shop_id']) && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['shop_id' => __('A shop is required.')]);
        }
        $shop = ! empty($data['shop_id']) ? $this->authorizeShop($user, (int) $data['shop_id']) : null;
        $date = $this->businessDate($data, 'entry_date');

        return DB::transaction(function () use ($data, $user, $shop, $date, $options) {
            // Business-level capital (no shop) is not tied to a shop day.
            $session = $shop ? $this->sessions->sessionFor($shop->id, $date, $options) : null;
            $entry = CapitalEntry::create(array_merge([
                'shop_id' => $shop?->id,
                'daily_session_id' => $session?->id,
                'user_id' => $user->id,
                'type' => $data['type'],
                'amount' => Money::round($data['amount']),
                'entry_date' => $date,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reason' => $data['reason'],
                'status' => 'active',
            ], $this->syncAttributes($data)));
            AuditLogger::log('capital.'.$data['type'], $entry);

            return $entry;
        });
    }

    public function void(CapitalEntry $entry, string $reason, User $user): CapitalEntry
    {
        $this->requirePermission($user, 'void_transactions', __('You are not allowed to void capital entries.'));
        if ($entry->shop_id) {
            $this->authorizeShop($user, $entry->shop_id);
        }
        if ($entry->status === 'voided') {
            $this->fail('already_voided', __('This capital entry is already voided.'));
        }
        $before = $entry->toArray();
        $entry->update(['status' => 'voided', 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
        AuditLogger::log('capital.voided', $entry, $before, ['status' => 'voided', 'reason' => $reason]);
        $this->sessions->refreshTotalsIfClosed($entry->daily_session_id);

        return $entry;
    }
}
