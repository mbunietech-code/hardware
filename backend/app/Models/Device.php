<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = ['user_id', 'device_id', 'name', 'platform', 'app_version', 'last_seen_at', 'last_sync_at', 'is_revoked'];

    protected $casts = ['last_seen_at' => 'datetime', 'last_sync_at' => 'datetime', 'is_revoked' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
