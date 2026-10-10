<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Shop;
use App\Models\User;
use App\Support\ActionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Helpers shared by the transaction services. */
trait Concerns
{
    public const PAYMENT_METHODS = ['cash', 'mobile_money', 'bank', 'card', 'credit'];

    protected function authorizeShop(User $user, ?int $shopId): Shop
    {
        if (! $shopId) {
            throw ValidationException::withMessages(['shop_id' => __('A shop is required.')]);
        }
        if (! $user->canAccessShop($shopId)) {
            throw new AuthorizationException(__('You are not allowed to work with this shop.'));
        }
        $shop = Shop::find($shopId);
        if (! $shop || ! $shop->is_active) {
            throw ValidationException::withMessages(['shop_id' => __('The selected shop is not active.')]);
        }

        return $shop;
    }

    protected function requirePermission(User $user, string $permission, string $message): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * Resolve a reference that may be given as a server id or as the local_uuid of
     * a record created offline on the same device (e.g. a customer created before the sale).
     *
     * @param  class-string<Model>  $class
     */
    protected function resolveRef(string $class, array $data, string $idKey, string $uuidKey, string $label): ?int
    {
        if (! empty($data[$idKey])) {
            if (! $class::whereKey($data[$idKey])->exists()) {
                throw ValidationException::withMessages([$idKey => __('The selected :item does not exist.', ['item' => __($label)])]);
            }

            return (int) $data[$idKey];
        }
        if (! empty($data[$uuidKey])) {
            $id = $class::where('local_uuid', $data[$uuidKey])->value('id');
            if (! $id) {
                throw ValidationException::withMessages([$uuidKey => __('The referenced :item has not been synced yet.', ['item' => __($label)])]);
            }

            return (int) $id;
        }

        return null;
    }

    protected function syncAttributes(array $data): array
    {
        $ctx = ActionContext::current();

        return [
            'local_uuid' => $data['local_uuid'] ?? null,
            'device_id' => $ctx->deviceId,
            'source' => $ctx->source,
            'client_created_at' => isset($data['client_created_at']) ? Carbon::parse($data['client_created_at']) : null,
            'synced_at' => $ctx->source === 'sync' ? now() : null,
        ];
    }

    protected function findByLocalUuid(string $class, array $data): ?Model
    {
        return empty($data['local_uuid']) ? null : $class::where('local_uuid', $data['local_uuid'])->first();
    }

    protected function businessDate(array $data, string $key): string
    {
        return Carbon::parse($data[$key] ?? now())->toDateString();
    }

    protected function fail(string $code, string $message, bool $conflict = false, array $context = []): never
    {
        throw new BusinessRuleException($code, $message, $conflict, $context);
    }
}
