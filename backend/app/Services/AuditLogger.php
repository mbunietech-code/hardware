<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\ActionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token'];

    public static function log(
        string $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?int $shopId = null,
        ?int $userId = null,
    ): AuditLog {
        $ctx = ActionContext::current();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'shop_id' => $shopId ?? ($entity?->getAttribute('shop_id')),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'before_value' => self::clean($before),
            'after_value' => self::clean($after ?? ($entity && $before === null ? $entity->toArray() : null)),
            'source' => $ctx->source,
            'device_id' => $ctx->deviceId,
            'ip_address' => $ctx->ip,
            'user_agent' => $ctx->userAgent ? mb_substr($ctx->userAgent, 0, 250) : null,
        ]);
    }

    private static function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_diff_key($values, array_flip(self::HIDDEN));
    }
}
