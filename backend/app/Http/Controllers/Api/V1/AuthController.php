<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:30',
        ]);

        $user = User::where('email', $data['login'])->orWhere('phone', $data['login'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLogger::log('auth.login_failed', null, null, ['login' => $data['login']], null, $user?->id);
            throw ValidationException::withMessages(['login' => __('The login details are incorrect.')]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('Your account is deactivated.')]);
        }

        if (! empty($data['device_id'])) {
            $device = Device::firstOrNew(['user_id' => $user->id, 'device_id' => $data['device_id']]);
            if ($device->exists && $device->is_revoked) {
                throw ValidationException::withMessages(['login' => __('This device has been revoked by an administrator.')]);
            }
            $device->fill([
                'name' => $data['device_name'] ?? $device->name,
                'platform' => $data['platform'] ?? $device->platform,
                'app_version' => $data['app_version'] ?? $device->app_version,
                'last_seen_at' => now(),
            ])->save();
        }

        $token = $user->createToken($data['device_id'] ?? 'api');
        $user->update(['last_login_at' => now()]);
        AuditLogger::log('auth.login', $user, null, ['device_id' => $data['device_id'] ?? null], $user->shop_id, $user->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $this->expiresAt(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $token = $user->createToken($current->name ?? 'api');
        $current->delete();

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $this->expiresAt(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        AuditLogger::log('auth.logout', $request->user());

        return response()->json(['message' => __('Logged out.')]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password:sanctum',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);
        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        AuditLogger::log('auth.password_changed', $request->user(), null, ['source' => 'api']);

        return response()->json(['message' => __('Password changed.'), 'user' => $this->userPayload($request->user()->fresh())]);
    }

    private function expiresAt(): ?string
    {
        $minutes = config('sanctum.expiration');

        return $minutes ? now()->addMinutes((int) $minutes)->toIso8601String() : null;
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('shop');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'must_change_password' => (bool) $user->must_change_password,
            'shop_id' => $user->shop_id,
            'shop' => $user->shop?->only(['id', 'name', 'code', 'location', 'phone']),
            'permissions' => collect(User::SHOP_PERMISSIONS)->keys()->mapWithKeys(fn ($p) => [$p => $user->hasPermission($p)]),
            'business_name' => Settings::get('business_name'),
            'currency' => Settings::get('currency'),
        ];
    }
}
