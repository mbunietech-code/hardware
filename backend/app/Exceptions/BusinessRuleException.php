<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business rule prevented the action (e.g. insufficient stock, day closed).
 * `conflict` marks cases where offline data disagrees with server state, which
 * sync records as a conflict for admin review instead of a plain rejection.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $conflict = false,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function render($request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'context' => $this->context,
        ], $this->conflict ? 409 : 422);
    }
}
