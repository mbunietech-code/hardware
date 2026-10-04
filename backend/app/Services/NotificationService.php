<?php

namespace App\Services;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\SyncReceipt;
use App\Models\SystemNotification;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;

class NotificationService
{
    /** Create a notification unless an unresolved one with the same dedupe key exists. */
    public static function raise(string $type, string $title, string $message, array $attrs = []): ?SystemNotification
    {
        $key = $attrs['dedupe_key'] ?? null;
        if ($key && SystemNotification::where('dedupe_key', $key)->whereNull('resolved_at')->exists()) {
            return null;
        }

        return SystemNotification::create(array_merge([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'audience' => 'shop',
        ], $attrs));
    }

    public static function resolve(string $dedupeKey): void
    {
        SystemNotification::where('dedupe_key', $dedupeKey)->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }

    public static function checkLowStock(StockBalance $balance): void
    {
        $balance->loadMissing('product', 'shop');
        $product = $balance->product;
        $key = "low_stock:{$balance->shop_id}:{$balance->product_id}";
        $reorder = (float) $product->reorder_level;

        if ($reorder > 0 && (float) $balance->quantity <= $reorder) {
            self::raise('low_stock', 'Low stock: :product',
                ':product at :shop has :qty :unit left (reorder level :reorder).',
                ['params' => ['product' => $product->name, 'shop' => $balance->shop->name, 'qty' => Money::formatQty($balance->quantity),
                    'unit' => $product->unit, 'reorder' => Money::formatQty($reorder)], 'shop_id' => $balance->shop_id, 'related_type' => $product->getMorphClass(), 'related_id' => $product->id, 'dedupe_key' => $key]);
        } else {
            self::resolve($key);
        }
    }

    public static function syncProblem(SyncReceipt $receipt): void
    {
        self::raise('sync_'.$receipt->status, 'Sync :status: :entity',
            'A :entity from device :device was :status: :error',
            ['params' => ['status' => $receipt->status, 'entity' => $receipt->entity, 'device' => (string) $receipt->device_id,
                'error' => (string) $receipt->error_message], 'shop_id' => $receipt->shop_id, 'audience' => 'admins', 'related_type' => $receipt->getMorphClass(),
                'related_id' => $receipt->id, 'dedupe_key' => 'sync:'.$receipt->id]);
    }

    /** Scheduled daily checks: overdue/soon-due debts and closing reminders. */
    public static function runScheduledChecks(?Carbon $now = null): array
    {
        $now ??= now();
        $count = ['debts' => 0, 'closing' => 0];
        $soon = $now->copy()->addDays((int) Settings::get('debt_reminder_days', 2))->toDateString();

        Debt::whereIn('status', ['open', 'partial'])->whereNotNull('due_date')->where('due_date', '<=', $soon)
            ->with('shop')->chunkById(200, function ($debts) use (&$count, $now) {
                foreach ($debts as $debt) {
                    $overdue = $debt->due_date->lt($now->copy()->startOfDay());
                    $n = self::raise($overdue ? 'debt_overdue' : 'debt_due', $overdue ? 'Overdue debt: :party' : 'Debt due soon: :party',
                        ':party balance :balance due :due (:shop).',
                        ['params' => ['party' => $debt->party_name, 'balance' => Money::format($debt->balance), 'due' => $debt->due_date->toDateString(),
                            'shop' => $debt->shop->name], 'shop_id' => $debt->shop_id, 'related_type' => $debt->getMorphClass(), 'related_id' => $debt->id,
                            'dedupe_key' => ($overdue ? 'debt_overdue:' : 'debt_due:').$debt->id]);
                    $count['debts'] += $n ? 1 : 0;
                }
            });

        // Sessions left open from previous days.
        DailySession::where('status', 'open')->where('business_date', '<', $now->toDateString())->with('shop')
            ->each(function (DailySession $s) use (&$count) {
                $n = self::raise('closing_overdue', 'Day not closed: :shop',
                    'The business day :date at :shop is still open.',
                    ['params' => ['shop' => $s->shop->name, 'date' => $s->business_date->toDateString()], 'shop_id' => $s->shop_id, 'related_type' => $s->getMorphClass(), 'related_id' => $s->id, 'dedupe_key' => 'closing_overdue:'.$s->id]);
                $count['closing'] += $n ? 1 : 0;
            });

        if ($now->hour >= (int) Settings::get('closing_reminder_hour', 18)) {
            Shop::where('is_active', true)->each(function (Shop $shop) use ($now, &$count) {
                $session = DailySession::where('shop_id', $shop->id)->whereDate('business_date', $now->toDateString())->first();
                if ($session && $session->isOpen()) {
                    $n = self::raise('closing_reminder', 'Close the day: :shop',
                        "Remember to review totals and close today's business day at :shop.",
                        ['params' => ['shop' => $shop->name], 'shop_id' => $shop->id, 'dedupe_key' => 'closing_reminder:'.$session->id]);
                    $count['closing'] += $n ? 1 : 0;
                }
            });
        }

        return $count;
    }
}
