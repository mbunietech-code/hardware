<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SHOP_ADMIN = 'shop_admin';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_SHOP_ADMIN => 'Shop Admin',
    ];

    /** Optional capabilities a Super Admin can grant to a Shop Admin (Doc 13 "if allowed"). */
    public const SHOP_PERMISSIONS = [
        'manage_products' => 'Manage products and categories',
        'record_capital' => 'Record capital entries',
        'adjust_stock' => 'Adjust stock',
        'view_reports' => 'View own-shop reports',
        'view_audit' => 'View own-shop audit trail',
        'void_transactions' => 'Void / correct transactions',
        'process_returns' => 'Process returns and refunds',
    ];

    protected $fillable = [
        'business_id', 'shop_id', 'role', 'name', 'email', 'phone', 'password', 'must_change_password',
        'permissions', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isShopAdmin(): bool
    {
        return $this->role === self::ROLE_SHOP_ADMIN;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) (($this->permissions ?? [])[$permission] ?? false);
    }

    public function canAccessShop(?int $shopId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $shopId !== null && (int) $this->shop_id === (int) $shopId;
    }

    /** @return array<int>|null null means all shops */
    public function accessibleShopIds(): ?array
    {
        return $this->isSuperAdmin() ? null : array_filter([(int) $this->shop_id]);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
