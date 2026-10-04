<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    protected $fillable = [
        'user_id', 'shop_id', 'audience', 'type', 'title', 'message', 'params', 'related_type', 'related_id',
        'dedupe_key', 'read_at', 'resolved_at',
    ];

    protected $casts = ['read_at' => 'datetime', 'resolved_at' => 'datetime', 'params' => 'array'];

    /** Title in the current UI language. */
    public function titleText(): string
    {
        return __($this->title, $this->translatedParams());
    }

    /** Message in the current UI language. */
    public function messageText(): string
    {
        return __($this->message, $this->translatedParams());
    }

    private function translatedParams(): array
    {
        return array_map(fn ($v) => is_string($v) ? __($v) : $v, $this->params ?? []);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** Notifications visible to the given user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->isSuperAdmin()) {
                $q->orWhere('audience', 'admins')->orWhere('audience', 'shop');
            } else {
                $q->orWhere(fn (Builder $s) => $s->where('audience', 'shop')->where('shop_id', $user->shop_id));
            }
        });
    }
}
