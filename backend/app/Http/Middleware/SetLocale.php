<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the UI language: English (en) or Swahili (sw).
 * Web uses the `locale` cookie set by the language switch; the mobile app sends Accept-Language.
 */
class SetLocale
{
    public const SUPPORTED = ['en' => 'English', 'sw' => 'Kiswahili'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');
        if ($request->is('api/*')) {
            $locale = substr((string) $request->header('Accept-Language', 'en'), 0, 2);
        }
        if (! isset(self::SUPPORTED[$locale])) {
            $locale = config('app.locale', 'en');
        }
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
