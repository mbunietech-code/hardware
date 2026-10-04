<?php

namespace App\Http\Middleware;

use App\Support\ActionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActionContext
{
    public function handle(Request $request, Closure $next, string $source = 'web'): Response
    {
        $ctx = ActionContext::current();
        $ctx->source = $source;
        $ctx->ip = $request->ip();
        $ctx->userAgent = $request->userAgent();
        $ctx->deviceId = $request->header('X-Device-Id');

        return $next($request);
    }
}
