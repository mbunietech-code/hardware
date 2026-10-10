<?php

namespace App\Support;

/**
 * Where the current action came from. Bound as a scoped singleton and filled by
 * middleware (web/api) or by the sync service, so audit logs can record source and device.
 */
class ActionContext
{
    public string $source = 'system';

    public ?string $deviceId = null;

    public ?string $ip = null;

    public ?string $userAgent = null;

    public static function current(): self
    {
        return app(self::class);
    }
}
