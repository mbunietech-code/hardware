<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Deactivated users and revoked devices lose access immediately (Doc 18). */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            if ($request->is('api/*')) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => __('Your account is deactivated.'), 'code' => 'account_inactive'], 401);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['login' => 'Your account is deactivated.']);
        }

        if ($request->is('api/*') && ($deviceId = $request->header('X-Device-Id'))) {
            $device = Device::where('user_id', $user->id)->where('device_id', $deviceId)->first();
            if ($device?->is_revoked) {
                return response()->json(['message' => __('This device has been revoked by an administrator.'), 'code' => 'device_revoked'], 401);
            }
            $device?->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}
