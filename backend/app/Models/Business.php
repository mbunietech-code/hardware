<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'currency'];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
