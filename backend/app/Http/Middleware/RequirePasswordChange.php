<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends users with a temporary/default password to the change-password page first. */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->must_change_password && ! $request->routeIs('profile.edit', 'profile.password', 'logout', 'locale.switch')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $next($request); // the app shows the flag from auth/me; web enforces it here
            }

            return redirect()->route('profile.edit')->with('warnings', [__('Please choose your own password before continuing.')]);
        }

        return $next($request);
    }
}
