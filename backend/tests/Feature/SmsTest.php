<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Debt;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Services\DailySessionService;
use App\Services\SaleService;
use App\Services\SmsService;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    private function enableBeem(): void
    {
        Settings::set('sms_enabled', true);
        Settings::set('beem_api_key', 'test-key');
        Settings::set('beem_secret_key', 'test-secret');
        Settings::set('sms_sender_id', 'DUKA');
        Http::fake([
            'apisms.beem.africa/v1/send' => Http::response(['successful' => true, 'request_id' => 'req-1', 'code' => 100, 'message' => 'Message Submitted Successfully', 'valid' => 1]),
            'apisms.beem.africa/public/v1/vendors/balance' => Http::response(['data' => ['credit_balance' => 250]]),
        ]);
    }

    public function test_phone_numbers_are_normalized_for_beem(): void
    {
        $this->assertSame('255712345678', SmsService::normalize('0712 345 678'));
        $this->assertSame('255712345678', SmsService::normalize('+255712345678'));
        $this->assertSame('255612345678', SmsService::normalize('612345678'));
        $this->assertNull(SmsService::normalize('123'));
    }

    public function test_send_uses_beem_api_with_basic_auth_and_logs(): void
    {
        $this->enableBeem();
        $log = app(SmsService::class)->send('0712345678', 'Habari', 'test');

        $this->assertSame('sent', $log->status);
        $this->assertSame('req-1', $log->provider_request_id);
        Http::assertSent(fn (Request $r) => $r->url() === SmsService::SEND_URL
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret'))
            && $r['source_addr'] === 'DUKA' && $r['encoding'] === 0
            && $r['recipients'][0]['dest_addr'] === '255712345678');
        $this->assertSame(250.0, app(SmsService::class)->balance());
    }

    public function test_not_configured_or_rejected_sms_is_logged_without_crashing(): void
    {
        $log = app(SmsService::class)->send('0712345678', 'x', 'test');
        $this->assertSame('skipped', $log->status);

        Settings::set('sms_enabled', true);
        Settings::set('beem_api_key', 'k');
        Settings::set('beem_secret_key', 's');
        Http::fake(['*' => Http::response(['code' => 120, 'message' => 'Invalid Authentication Parameters'], 401)]);
        $log = app(SmsService::class)->send('0712345678', 'x', 'test');
        $this->assertSame('failed', $log->status);
        $this->assertSame('Invalid Authentication Parameters', $log->error);
    }

    public function test_debt_reminders_manual_and_scheduled_once_per_interval(): void
    {
        $this->enableBeem();
        $debt = Debt::create(['shop_id' => $this->main->id, 'user_id' => $this->admin->id, 'type' => 'receivable', 'party_name' => 'Asha',
            'party_phone' => '0754000111', 'original_amount' => 50000, 'balance' => 50000, 'debt_date' => now()->subDays(10),
            'due_date' => now()->subDay(), 'status' => 'open']);

        $this->actingAs($this->admin)->post("/debts/{$debt->id}/sms")->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => str_contains($r['message'], 'Asha') && str_contains($r['message'], '50,000'));

        // A manual reminder was just sent, so the scheduled job waits for the interval.
        $this->assertSame(0, app(SmsService::class)->sendScheduledDebtReminders());
        SmsMessage::query()->update(['created_at' => now()->subDays(5)]);
        $this->assertSame(1, app(SmsService::class)->sendScheduledDebtReminders());
    }

    public function test_owner_gets_summary_when_day_is_closed(): void
    {
        $this->enableBeem();
        Settings::set('sms_owner_phone', '0700111222');
        $session = $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        app(SaleService::class)->create(['shop_id' => $this->main->id, 'payment_method' => 'cash',
            'items' => [['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 20000]]], $this->shopAdmin);
        app(DailySessionService::class)->close($session, ['closing_cash' => 0], $this->shopAdmin);

        Http::assertSent(fn (Request $r) => $r['recipients'][0]['dest_addr'] === '255700111222' && str_contains($r['message'], 'Mauzo'));
        $this->assertDatabaseHas('sms_messages', ['purpose' => 'daily_summary', 'status' => 'sent']);
    }

    public function test_secrets_are_encrypted_and_never_shown(): void
    {
        Settings::set('beem_secret_key', 'super-secret-value');
        $this->assertStringNotContainsString('super-secret-value', Setting::where('key', 'beem_secret_key')->value('value'));
        $this->assertSame('super-secret-value', Settings::get('beem_secret_key'));

        $this->actingAs($this->admin)->get('/settings')->assertOk()->assertDontSee('super-secret-value');
        // Saving the form with the secret left empty keeps it.
        $form = collect(Settings::DEFINITIONS)->map(fn ($d, $k) => $d['type'] === 'secret' ? '' : Settings::get($k))->all();
        $this->put('/settings', $form)->assertSessionHas('success');
        $this->assertSame('super-secret-value', Settings::get('beem_secret_key'));
        $this->assertStringNotContainsString('super-secret-value', json_encode(AuditLog::latest('id')->first()->after_value));
    }
}
