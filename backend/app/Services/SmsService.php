<?php

namespace App\Services;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SMS through Beem Africa (https://docs.beem.africa).
 * Every attempt is logged in sms_messages; failures never break the business action that triggered them.
 */
class SmsService
{
    public const SEND_URL = 'https://apisms.beem.africa/v1/send';

    public const BALANCE_URL = 'https://apisms.beem.africa/public/v1/vendors/balance';

    public function configured(): bool
    {
        return Settings::get('sms_enabled') && Settings::get('beem_api_key') !== '' && Settings::get('beem_secret_key') !== '';
    }

    /** Tanzanian numbers to Beem's international format without "+" (0712… → 255712…). */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (preg_match('/^0([67]\d{8})$/', $digits, $m)) {
            return '255'.$m[1];
        }
        if (preg_match('/^([67]\d{8})$/', $digits, $m)) {
            return '255'.$m[1];
        }
        if (preg_match('/^255[67]\d{8}$/', $digits) || preg_match('/^\d{11,15}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public function send(?string $phone, string $message, string $purpose, ?Model $related = null, ?int $shopId = null, ?User $user = null): SmsMessage
    {
        $to = self::normalize($phone);
        $log = SmsMessage::create([
            'to' => $to ?? (string) $phone, 'message' => $message, 'purpose' => $purpose, 'status' => 'skipped',
            'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(),
            'shop_id' => $shopId, 'user_id' => $user?->id ?? auth()->id(),
        ]);
        if (! $to) {
            $log->update(['error' => __('Invalid phone number.')]);

            return $log;
        }
        if (! $this->configured()) {
            $log->update(['error' => __('SMS is not set up. Add the Beem keys in Settings → SMS.')]);

            return $log;
        }

        try {
            $response = Http::withBasicAuth(Settings::get('beem_api_key'), Settings::get('beem_secret_key'))
                ->acceptJson()->timeout(20)
                ->post(self::SEND_URL, [
                    'source_addr' => Settings::get('sms_sender_id') ?: 'INFO',
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [['recipient_id' => $log->id, 'dest_addr' => $to]],
                ]);
            $body = $response->json() ?? [];
            if ($response->successful() && ($body['successful'] ?? false)) {
                $log->update(['status' => 'sent', 'provider_request_id' => (string) ($body['request_id'] ?? ''), 'error' => null]);
            } else {
                $log->update(['status' => 'failed', 'error' => $body['message'] ?? $body['error'] ?? ('HTTP '.$response->status())]);
            }
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $log;
    }

    /** Remaining Beem credits, or null when not configured / unreachable (cached for 5 minutes). */
    public function balance(): ?float
    {
        if (! $this->configured()) {
            return null;
        }

        return Cache::remember('beem_balance_'.md5(Settings::get('beem_api_key')), 300, function () {
            try {
                $r = Http::withBasicAuth(Settings::get('beem_api_key'), Settings::get('beem_secret_key'))->acceptJson()->timeout(10)->get(self::BALANCE_URL);

                return $r->successful() ? (float) data_get($r->json(), 'data.credit_balance') : null;
            } catch (Throwable) {
                return null;
            }
        });
    }

    public function debtReminderText(Debt $debt): string
    {
        $debt->loadMissing('shop');

        return strtr(Settings::get('sms_debt_template'), [
            '{name}' => $debt->party_name,
            '{amount}' => Money::format($debt->balance),
            '{due}' => $debt->due_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
            '{shop}' => $debt->shop->name,
            '{business}' => Settings::get('business_name'),
        ]);
    }

    public function sendDebtReminder(Debt $debt, ?User $user = null): SmsMessage
    {
        return $this->send($debt->party_phone, $this->debtReminderText($debt), 'debt_reminder', $debt, $debt->shop_id, $user);
    }

    /** Automatic reminders for customer debts that are due soon or overdue. Returns how many were sent. */
    public function sendScheduledDebtReminders(): int
    {
        if (! $this->configured() || ! Settings::get('sms_debt_reminders')) {
            return 0;
        }
        $every = max(1, (int) Settings::get('sms_reminder_every_days'));
        $dueBy = now()->addDays((int) Settings::get('debt_reminder_days', 2))->toDateString();
        $sent = 0;
        Debt::where('type', 'receivable')->whereIn('status', ['open', 'partial'])->where('balance', '>', 0)
            ->whereNotNull('party_phone')->whereNotNull('due_date')->where('due_date', '<=', $dueBy)
            ->each(function (Debt $debt) use ($every, &$sent) {
                $recent = SmsMessage::where('related_type', $debt->getMorphClass())->where('related_id', $debt->id)
                    ->where('purpose', 'debt_reminder')->where('status', 'sent')
                    ->where('created_at', '>=', now()->subDays($every))->exists();
                if (! $recent && $this->sendDebtReminder($debt)->status === 'sent') {
                    $sent++;
                }
            });

        return $sent;
    }

    /** Owner summary when a shop closes its day. */
    public function sendDailySummary(DailySession $session): ?SmsMessage
    {
        $phone = Settings::get('sms_owner_phone');
        if (! $this->configured() || ! Settings::get('sms_daily_summary') || ! $phone) {
            return null;
        }
        $session->loadMissing('shop');
        $t = $session->totals ?? [];
        $remaining = (float) $session->opening_cash + (float) ($t['sales_paid'] ?? 0) + (float) ($t['debt_payments_received'] ?? 0)
            + (float) ($t['capital_in'] ?? 0) - (float) ($t['purchases_paid'] ?? 0) - (float) ($t['expenses_total'] ?? 0)
            - (float) ($t['refunds_paid'] ?? 0) - (float) ($t['debt_payments_made'] ?? 0) - (float) ($t['capital_out'] ?? 0);
        $profit = app(ProfitService::class)->summary($session->business_date->toDateString(), $session->business_date->toDateString(), $session->shop_id)['profit'];
        $message = sprintf(
            "%s %s imefungwa.\nMauzo: %s (%d)\nMatumizi: %s\nPesa iliyobaki: %s\nFaida: %s\nTaslimu droo: %s",
            $session->shop->name, $session->business_date->format('d/m/Y'),
            Money::format($t['sales_total'] ?? 0), (int) ($t['sales_count'] ?? 0), Money::format($t['expenses_total'] ?? 0),
            Money::format($remaining), Money::format($profit),
            Money::format($session->closing_cash ?? $session->expected_cash ?? 0),
        );

        return $this->send($phone, $message, 'daily_summary', $session, $session->shop_id);
    }
}
