<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends WebController
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string']);
        $user = User::where('email', $data['login'])->orWhere('phone', $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLogger::log('auth.login_failed', null, null, ['login' => $data['login']], null, $user?->id);
            throw ValidationException::withMessages(['login' => __('The login details are incorrect.')]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('Your account is deactivated.')]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        AuditLogger::log('auth.login', $user, null, ['source' => 'web'], $user->shop_id, $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLogger::log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $request->user()->update(['password' => $data['password']]);
        AuditLogger::log('auth.password_changed', $request->user(), null, []);

        return back()->with('success', __('Password changed.'));
    }
}
