<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Super Admin endpoints: users, shops, settings. */
class AdminController extends ApiController
{
    public function users(Request $request)
    {
        return User::with('shop:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->paginate($this->perPage($request));
    }

    public function storeUser(Request $request)
    {
        $data = $this->validateUser($request);
        $user = User::create($data + ['business_id' => Business::value('id')]);
        AuditLogger::log('user.created', $user);

        return response()->json(['data' => $user], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $before = $user->toArray();
        $user->update($data);
        AuditLogger::log('user.updated', $user, $before, $user->toArray());

        return response()->json(['data' => $user]);
    }

    public function userStatus(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        abort_if($user->id === $request->user()->id && ! $data['is_active'], 422, __('You cannot deactivate yourself.'));
        $user->update($data);
        if (! $data['is_active']) {
            $user->tokens()->delete();
        }
        AuditLogger::log($data['is_active'] ? 'user.activated' : 'user.deactivated', $user);

        return response()->json(['data' => $user]);
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user?->id), 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'shop_id' => 'nullable|required_if:role,shop_admin|exists:shops,id',
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'permissions' => 'nullable|array',
            'permissions.*' => 'boolean',
            'is_active' => 'sometimes|boolean',
        ]);
    }

    public function shops(Request $request)
    {
        $user = $request->user();

        return Shop::when(! $user->isSuperAdmin(), fn ($q) => $q->whereKey($user->shop_id))->orderBy('name')->get();
    }

    public function storeShop(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|alpha_dash|unique:shops,code',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);
        $shop = Shop::create($data + ['business_id' => Business::value('id'), 'is_active' => true]);
        AuditLogger::log('shop.created', $shop);

        return response()->json(['data' => $shop], 201);
    }

    public function updateShop(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:20', 'alpha_dash', Rule::unique('shops')->ignore($shop->id)],
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
        ]);
        $before = $shop->toArray();
        $shop->update($data);
        AuditLogger::log('shop.updated', $shop, $before, $shop->toArray());

        return response()->json(['data' => $shop]);
    }

    public function settings()
    {
        return response()->json(['data' => Settings::all(), 'definitions' => Settings::DEFINITIONS]);
    }

    public function updateSettings(Request $request)
    {
        $before = Settings::all();
        foreach ($request->only(array_keys(Settings::DEFINITIONS)) as $key => $value) {
            Settings::set($key, $value);
        }
        AuditLogger::log('settings.updated', null, $before, Settings::all());

        return response()->json(['data' => Settings::all()]);
    }
}
