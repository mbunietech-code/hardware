<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncReceipt extends Model
{
    protected $fillable = [
        'local_uuid', 'entity', 'user_id', 'shop_id', 'device_id', 'status', 'server_id', 'error_code',
        'error_message', 'payload', 'resolved_by', 'resolved_at', 'resolution',
    ];

    protected $casts = ['payload' => 'array', 'resolved_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
