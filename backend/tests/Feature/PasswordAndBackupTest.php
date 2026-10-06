<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordAndBackupTest extends TestCase
{
    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();
        $this->get('/login')->assertSee('Forgot password?');
        $this->get('/forgot-password')->assertOk();

        $this->post('/forgot-password', ['email' => 'shop@hardware.test'])->assertSessionHas('success');
        $this->post('/forgot-password', ['email' => 'nobody@hardware.test'])->assertSessionHas('success'); // no account leak

        $token = null;
        Notification::assertSentTo($this->shopAdmin, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/$token?email=shop@hardware.test")->assertOk();
        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'shop@hardware.test', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'shop@hardware.test', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('newpass123', $this->shopAdmin->fresh()->password));
    }

    public function test_backups_page_is_admin_only_and_rejects_bad_names(): void
    {
        $this->actingAs($this->admin)->get('/backups')->assertOk()->assertSee('Back up now');
        $this->get('/backups/..%2F.env')->assertNotFound();
        $this->actingAs($this->shopAdmin)->get('/backups')->assertForbidden();
    }

    public function test_default_password_must_be_changed_before_using_the_system(): void
    {
        $this->shopAdmin->update(['must_change_password' => true]);
        $this->actingAs($this->shopAdmin)->get('/')->assertRedirect('/profile');
        $this->get('/sales')->assertRedirect('/profile');
        $this->get('/profile')->assertOk();

        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'Duka2026!', 'password_confirmation' => 'Duka2026!'])
            ->assertSessionHas('success');
        $this->assertFalse($this->shopAdmin->fresh()->must_change_password);
        $this->get('/')->assertOk();

        // A password set by the Super Admin is temporary again.
        $this->actingAs($this->admin)->put("/users/{$this->shopAdmin->id}", [
            'name' => $this->shopAdmin->name, 'email' => $this->shopAdmin->email, 'role' => 'shop_admin', 'shop_id' => $this->main->id,
            'password' => 'Temp12345', 'password_confirmation' => 'Temp12345', 'is_active' => 1,
        ])->assertRedirect('/users');
        $this->assertTrue($this->shopAdmin->fresh()->must_change_password);
    }
}
