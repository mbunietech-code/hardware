<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_language_switch_sets_cookie_and_translates_pages(): void
    {
        $this->get('/login')->assertSee('Sign in')->assertSee('EN')->assertSee('SW');

        $this->from('/login')->get('/locale/sw')->assertRedirect('/login')->assertCookie('locale', 'sw');
        $this->withCookie('locale', 'sw')->get('/login')->assertSee('Ingia')->assertSee('Barua pepe au simu');

        $this->actingAs($this->admin);
        foreach (['/', '/sales', '/stock', '/debts', '/reports/profit', '/settings', '/sync'] as $page) {
            $this->withCookie('locale', 'sw')->get($page)->assertOk();
        }
        $this->withCookie('locale', 'sw')->get('/')->assertSee('Mauzo ya leo')->assertSee('Siku za biashara')->assertDontSee('Sales today');
        $this->withCookie('locale', 'en')->get('/')->assertSee('Sales today');

        $this->get('/locale/xx')->assertNotFound();
    }

    public function test_api_errors_follow_accept_language(): void
    {
        Sanctum::actingAs($this->shopAdmin);
        $payload = ['shop_id' => $this->main->id, 'payment_method' => 'cash', 'items' => [['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 1]]];

        $this->withHeader('Accept-Language', 'sw')->postJson('/api/v1/sales', $payload)
            ->assertStatus(422)->assertJsonPath('code', 'no_open_day')
            ->assertJsonPath('message', 'Fungua siku ya biashara ya '.now()->toDateString().' kabla ya kurekodi miamala.');

        $this->withHeader('Accept-Language', 'sw')->postJson('/api/v1/sales', ['shop_id' => $this->main->id])
            ->assertStatus(422)->assertJsonPath('errors.payment_method.0', 'Njia ya malipo inahitajika.');
    }
}
