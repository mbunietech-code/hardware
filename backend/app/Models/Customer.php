<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'is_active', 'local_uuid', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }
}
